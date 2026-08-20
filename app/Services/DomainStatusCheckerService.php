<?php

namespace App\Services;

use App\Jobs\ProcessDomainStatusCheckChunkJob;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DomainStatusCheckerService
{
    public const MAX_DOMAINS = 500;

    public function __construct(
        private readonly WordPressAgentStatusService $agentStatusService
    ) {}

    /**
     * @return array<int, string>
     */
    public function parseDomainList(string $input): array
    {
        $domains = [];

        foreach (preg_split('/[\r\n,]+/', $input) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            foreach (preg_split('/\s+/', $line) as $part) {
                $name = normalizeDomainName($part);
                if ($name !== '') {
                    $domains[$name] = $name;
                }
            }
        }

        return array_values($domains);
    }

    /**
     * @return array{names: array<int, string>, total_in_scope: int}
     */
    public function getDomainNamesFromInventory(?int $categoryId = null): array
    {
        $query = Domain::query()->orderBy('name');

        if ($categoryId !== null) {
            $query->where('domain_category_id', $categoryId);
        }

        $totalInScope = (clone $query)->count();

        $names = $query
            ->limit(self::MAX_DOMAINS)
            ->pluck('name')
            ->all();

        return [
            'names' => $names,
            'total_in_scope' => $totalInScope,
        ];
    }

    /**
     * @param  array<int, string>  $domainNames
     */
    public function createCheck(
        int $adminId,
        array $domainNames,
        string $source,
        bool $updateInventory,
        ?int $categoryId = null,
        bool $useAuthenticatedCheck = false,
    ): DomainStatusCheck {
        $inventoryByName = Domain::query()
            ->with('domainCategory:id,name')
            ->whereIn('name', $domainNames)
            ->get()
            ->keyBy('name');

        $inInventoryCount = $inventoryByName->count();

        $check = DB::transaction(function () use (
            $adminId,
            $domainNames,
            $source,
            $updateInventory,
            $useAuthenticatedCheck,
            $categoryId,
            $inventoryByName,
            $inInventoryCount
        ) {
            $check = DomainStatusCheck::create([
                'admin_id' => $adminId,
                'source' => $source,
                'status' => 'queued',
                'phase' => 'initial',
                'total_count' => count($domainNames),
                'in_inventory_count' => $inInventoryCount,
                'update_inventory' => $updateInventory,
                'use_authenticated_check' => $useAuthenticatedCheck,
                'domain_category_id' => $categoryId,
                'status_message' => $useAuthenticatedCheck
                    ? 'Queued — authenticated POST /status/check (domainCheck worker)'
                    : 'Queued — waiting for queue worker (domainCheck)',
            ]);

            $rows = [];
            $now = now();

            foreach ($domainNames as $index => $domain) {
                $inventoryDomain = $inventoryByName->get($domain);

                $rows[] = [
                    'domain_status_check_id' => $check->id,
                    'domain' => $domain,
                    'sort_order' => $index + 1,
                    'check_status' => 'pending',
                    'in_inventory' => $inventoryDomain !== null,
                    'domain_id' => $inventoryDomain?->id,
                    'category' => $inventoryDomain?->domainCategory?->name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DomainStatusCheckItem::insert($chunk);
            }

            // Optional background processing when a queue worker is running.
            ProcessDomainStatusCheckChunkJob::dispatch($check->id, 'initial')
                ->onQueue('domainCheck');

            return $check;
        });

        app(DomainStatusCheckCleanupService::class)->queueLightweightPrune($adminId);

        return $check;
    }

    /**
     * Process the next ready chunk. Returns seconds to delay before the next
     * chunk job (0 = dispatch immediately), or null when the check is finished.
     */
    public function processNextChunk(DomainStatusCheck $check): ?int
    {
        if ($check->isFinished()) {
            return null;
        }

        $this->recoverOrphanedItems($check);

        if (in_array($check->status, ['queued', 'processing'], true)) {
            $waiting = $this->countWaitingRetryItems($check);
            $check->update([
                'status' => 'processing',
                'phase' => $waiting > 0 ? 'backoff' : 'initial',
                'started_at' => $check->started_at ?? now(),
                'status_message' => $waiting > 0
                    ? "Waiting to retry {$waiting} domain(s) (campaign-style backoff)..."
                    : 'Checking domains...',
            ]);
        }

        $items = $this->readyItemsQuery($check)
            ->orderBy('sort_order')
            ->limit($this->chunkSize())
            ->get();

        if ($items->isEmpty()) {
            $delay = $this->secondsUntilNextReadyWork($check);

            if ($delay === null) {
                $this->finalizeCheck($check);

                return null;
            }

            $check->update([
                'phase' => 'backoff',
                'status_message' => 'Next retry in '.$this->formatDelayLabel($delay).'...',
            ]);

            return $delay;
        }

        DomainStatusCheckItem::query()
            ->whereIn('id', $items->pluck('id'))
            ->update(['check_status' => 'checking']);

        $results = $this->probeDomains($items, (bool) $check->use_authenticated_check);

        foreach ($items as $item) {
            $this->applyProbeResults($item, $results[$item->id] ?? [
                'connected' => false,
                'message' => 'No response received',
                'response_time_ms' => null,
            ]);
        }

        $check = $check->fresh();
        $this->recalculateCheckStats($check);

        if ($this->hasUnfinishedItems($check)) {
            $delay = $this->secondsUntilNextReadyWork($check) ?? 0;
            $waiting = $this->countWaitingRetryItems($check);
            $check->update([
                'phase' => $waiting > 0 && $delay > 0 ? 'backoff' : 'initial',
                'status_message' => $delay > 0
                    ? 'Next retry in '.$this->formatDelayLabel($delay).'...'
                    : 'Checking domains...',
            ]);

            return $delay;
        }

        $this->finalizeCheck($check->fresh());

        return null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Admin\DomainStatusCheckItem>
     */
    private function readyItemsQuery(DomainStatusCheck $check)
    {
        return DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where(function ($q) {
                $q->where('check_status', 'pending')
                    ->orWhere(function ($inner) {
                        $inner->where('check_status', 'retry_pending')
                            ->where(function ($ready) {
                                $ready->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            });
                    });
            });
    }

    private function hasUnfinishedItems(DomainStatusCheck $check): bool
    {
        return DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->whereIn('check_status', ['pending', 'checking', 'retry_pending'])
            ->exists();
    }

    private function countWaitingRetryItems(DomainStatusCheck $check): int
    {
        return DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', 'retry_pending')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '>', now())
            ->count();
    }

    /**
     * Seconds until at least one item is ready. 0 = ready now. null = nothing left unfinished.
     */
    public function secondsUntilNextReadyWork(DomainStatusCheck $check): ?int
    {
        if ($this->readyItemsQuery($check)->exists()) {
            return 0;
        }

        $nextAt = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', 'retry_pending')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '>', now())
            ->min('next_retry_at');

        if ($nextAt === null) {
            return null;
        }

        $seconds = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($nextAt), false);

        return max(1, (int) $seconds);
    }

    private function formatDelayLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = (int) round($seconds / 60);

        return $minutes.'m';
    }

    /**
     * @return array{pending: int, checking: int, retry_pending: int, processed: int, progress_percent: int}
     */
    public function progressMetrics(DomainStatusCheck $check): array
    {
        $counts = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->selectRaw("
                SUM(CASE WHEN check_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN check_status = 'checking' THEN 1 ELSE 0 END) as checking_count,
                SUM(CASE WHEN check_status = 'retry_pending' THEN 1 ELSE 0 END) as retry_pending_count,
                SUM(CASE WHEN check_status IN ('connected', 'disconnected') THEN 1 ELSE 0 END) as processed_count
            ")
            ->first();

        $pending = (int) ($counts->pending_count ?? 0);
        $checking = (int) ($counts->checking_count ?? 0);
        $retryPending = (int) ($counts->retry_pending_count ?? 0);
        $processed = (int) ($counts->processed_count ?? 0);
        $total = max(1, $check->total_count);

        // Weight finalized items fully; in-flight / waiting items by attempts toward max.
        $maxAttempts = max(1, $this->maxAttempts());
        $attemptWeight = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->whereIn('check_status', ['pending', 'checking', 'retry_pending'])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN attempts < ? THEN attempts ELSE ? END) / ?, 0) as weighted',
                [$maxAttempts, $maxAttempts, $maxAttempts]
            )
            ->value('weighted');

        $progressPercent = (int) min(100, round((($processed + (float) $attemptWeight) / $total) * 100));

        if ($check->isFinished()) {
            $progressPercent = 100;
        }

        return [
            'pending' => $pending,
            'checking' => $checking,
            'retry_pending' => $retryPending,
            'processed' => $processed,
            'progress_percent' => $progressPercent,
        ];
    }

    /**
     * @param  Collection<int, DomainStatusCheckItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public function probeDomains(Collection $items, bool $useAuthenticatedCheck = false): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $isLaterAttempt = $items->contains(fn ($item) => (int) $item->attempts >= 1);
        $timeout = $isLaterAttempt ? $this->retryRequestTimeout() : $this->requestTimeout();
        $connectTimeout = $isLaterAttempt ? $this->retryConnectTimeout() : $this->connectTimeout();

        if ($useAuthenticatedCheck) {
            $apiKeysByDomainId = Domain::query()
                ->whereIn('id', $items->pluck('domain_id')->filter()->all())
                ->get(['id', 'api_key'])
                ->mapWithKeys(function (Domain $domain) {
                    try {
                        $key = trim((string) ($domain->api_key ?? ''));
                    } catch (\Throwable) {
                        $key = '';
                    }

                    return [(int) $domain->id => $key];
                });

            $targets = $items->mapWithKeys(function ($item) use ($apiKeysByDomainId) {
                $apiKey = $item->domain_id
                    ? ($apiKeysByDomainId[(int) $item->domain_id] ?? '')
                    : '';

                return [
                    $item->id => [
                        'domain' => $item->domain,
                        'api_key' => $apiKey,
                    ],
                ];
            })->all();

            $responses = $this->agentStatusService->probeManyWithAuthFallback(
                $targets,
                $timeout,
                $connectTimeout
            );
        } else {
            $domains = $items->mapWithKeys(fn ($item) => [$item->id => $item->domain])->all();
            $responses = $this->agentStatusService->probeMany($domains, $timeout, $connectTimeout);
        }

        $results = [];

        foreach ($items as $item) {
            $result = $responses[$item->id] ?? null;
            $results[$item->id] = $result === null
                ? [
                    'connected' => false,
                    'status_code' => 'domain_offline',
                    'message' => 'No response received',
                    'probe_method' => $useAuthenticatedCheck ? 'auth_rest' : 'rest',
                    'agent_version' => null,
                    'http_status' => null,
                    'response_time_ms' => null,
                ]
                : [
                    'connected' => $result->ok,
                    'status_code' => $result->code,
                    'message' => $result->message,
                    'probe_method' => $result->probeMethod,
                    'agent_version' => $result->pluginVersion,
                    'http_status' => $result->httpStatus,
                    'response_time_ms' => $result->responseTimeMs,
                    'agent_result' => $result,
                ];
        }

        return $results;
    }

    public function applyProbeResults(DomainStatusCheckItem $item, array $result): void
    {
        $connected = (bool) ($result['connected'] ?? false);
        $message = $this->truncateItemMessage((string) ($result['message'] ?? 'Unknown response'));
        $attempts = (int) $item->attempts + 1;
        $maxAttempts = $this->maxAttempts();
        $classification = [
            'status_code' => $result['status_code'] ?? 'domain_offline',
            'probe_method' => $result['probe_method'] ?? 'rest',
            'agent_version' => $result['agent_version'] ?? null,
            'http_status' => $result['http_status'] ?? null,
        ];

        if ($item->domain_id && isset($result['agent_result'])) {
            $domain = Domain::query()->find($item->domain_id);
            $domain?->persistAgentHealth($result['agent_result']);
        }

        if ($connected) {
            $item->update($classification + [
                'check_status' => 'connected',
                'connected' => true,
                'message' => $this->truncateItemMessage($attempts > 1
                    ? $message.' (connected on attempt '.$attempts.'/'.$maxAttempts.')'
                    : $message),
                'attempts' => $attempts,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'checked_at' => now(),
                'next_retry_at' => null,
            ]);

            return;
        }

        if ($attempts < $maxAttempts) {
            $delay = $this->backoffSecondsAfterAttempt($attempts);
            $item->update($classification + [
                'check_status' => 'retry_pending',
                'connected' => false,
                'message' => $this->truncateItemMessage(
                    $message.' — retry in '.$this->formatDelayLabel($delay)
                    .' (attempt '.$attempts.'/'.$maxAttempts.')'
                ),
                'attempts' => $attempts,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'checked_at' => now(),
                'next_retry_at' => now()->addSeconds($delay),
            ]);

            return;
        }

        $item->update($classification + [
            'check_status' => 'disconnected',
            'connected' => false,
            'message' => $this->truncateItemMessage(
                $message.' (still failing after '.$maxAttempts.' attempts)'
            ),
            'attempts' => $attempts,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'checked_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    private function truncateItemMessage(string $message, int $maxLength = 2000): string
    {
        $message = trim($message);
        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }

        return mb_substr($message, 0, $maxLength - 1).'…';
    }

    public function recalculateCheckStats(DomainStatusCheck $check): void
    {
        $stats = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->selectRaw("
                SUM(CASE WHEN check_status IN ('connected', 'disconnected') THEN 1 ELSE 0 END) as processed_count,
                SUM(CASE WHEN check_status = 'connected' THEN 1 ELSE 0 END) as connected_count,
                SUM(CASE WHEN check_status = 'disconnected' THEN 1 ELSE 0 END) as disconnected_count
            ")
            ->first();

        $check->update([
            'processed_count' => (int) ($stats->processed_count ?? 0),
            'connected_count' => (int) ($stats->connected_count ?? 0),
            'disconnected_count' => (int) ($stats->disconnected_count ?? 0),
        ]);
    }

    public function finalizeCheck(DomainStatusCheck $check): void
    {
        $inventoryUpdated = 0;

        if ($check->update_inventory) {
            $inventoryUpdated = $this->syncInventoryFromCheck($check);
        }

        $check->update([
            'status' => 'completed',
            'phase' => 'done',
            'inventory_updated_count' => $inventoryUpdated,
            'status_message' => 'Check completed',
            'completed_at' => now(),
        ]);
    }

    public function syncInventoryFromCheck(DomainStatusCheck $check): int
    {
        $updated = 0;

        DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->whereNotNull('domain_id')
            ->whereIn('check_status', ['connected', 'disconnected'])
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$updated) {
                foreach ($items as $item) {
                    Domain::query()
                        ->where('id', $item->domain_id)
                        ->update(['status' => $item->connected ? 1 : 0]);

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @return array{
     *     names: array<int, string>,
     *     total_in_scope: int,
     *     by_category: array<int, array{category_id: ?int, category: string, count: int}>
     * }
     */
    public function getDisconnectedDomainNames(?int $categoryId = null): array
    {
        $base = Domain::query()
            ->where('domains.status', 0)
            ->when(
                $categoryId !== null,
                fn ($q) => $q->where('domains.domain_category_id', $categoryId)
            );

        $totalInScope = (clone $base)->count();

        $categoryCounts = (clone $base)
            ->selectRaw('domains.domain_category_id as category_id, COUNT(*) as aggregate_count')
            ->groupBy('domains.domain_category_id')
            ->pluck('aggregate_count', 'category_id');

        $categoryNames = DomainCategory::query()
            ->whereIn('id', $categoryCounts->keys()->filter()->all())
            ->pluck('name', 'id');

        $byCategory = $categoryCounts
            ->map(function ($count, $categoryId) use ($categoryNames) {
                $id = $categoryId !== null ? (int) $categoryId : null;

                return [
                    'category_id' => $id,
                    'category' => $id !== null
                        ? (string) ($categoryNames[$id] ?? 'Uncategorized')
                        : 'Uncategorized',
                    'count' => (int) $count,
                ];
            })
            ->sortBy('category', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $names = (clone $base)
            ->orderBy('domains.domain_category_id')
            ->orderBy('domains.name')
            ->limit($this->disconnectedMaxDomains())
            ->pluck('domains.name')
            ->all();

        return [
            'names' => $names,
            'total_in_scope' => $totalInScope,
            'by_category' => $byCategory,
        ];
    }

    /**
     * Latest disconnected recheck for an admin: prefer an unfinished run, else one finished within 24 hours.
     */
    public function latestDisconnectedCheckForAdmin(int $adminId): ?DomainStatusCheck
    {
        $active = DomainStatusCheck::query()
            ->where('admin_id', $adminId)
            ->where('source', 'disconnected')
            ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
            ->latest('id')
            ->first();

        if ($active) {
            return $active;
        }

        return DomainStatusCheck::query()
            ->where('admin_id', $adminId)
            ->where('source', 'disconnected')
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->where(function ($query) {
                $query->where('completed_at', '>=', now()->subDay())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('completed_at')
                            ->where('updated_at', '>=', now()->subDay());
                    });
            })
            ->latest('id')
            ->first();
    }

    /**
     * Progress JSON payload for the disconnected recheck UI (poll + page-load resume).
     *
     * @return array{check: array<string, mixed>, results: array<int, array<string, mixed>>, server_time: string}
     */
    public function disconnectedProgressPayload(DomainStatusCheck $check, ?string $since = null): array
    {
        $metrics = $this->progressMetrics($check);
        $summary = $this->recheckSummary($check);
        $items = $this->progressItems($check, $since);
        $pendingCount = $metrics['pending'] + $metrics['checking'] + $metrics['retry_pending'];

        return [
            'server_time' => now()->toIso8601String(),
            'check' => [
                'uuid' => $check->uuid,
                'status' => $check->status,
                'phase' => $check->phase,
                'status_message' => $check->status_message,
                'total' => $check->total_count,
                'processed' => $check->processed_count,
                'pending' => $pendingCount,
                'checking' => $metrics['checking'],
                'retry_pending' => $metrics['retry_pending'],
                'progress_percent' => $metrics['progress_percent'],
                'connected' => $check->connected_count,
                'disconnected' => $check->disconnected_count,
                'inventory_updated' => $check->inventory_updated_count,
                'update_inventory' => $check->update_inventory,
                'started_at' => $check->started_at?->toIso8601String(),
                'completed_at' => $check->completed_at?->toIso8601String(),
                'is_finished' => $check->isFinished(),
                'started_disconnected' => $summary['started_disconnected'],
                'now_connected' => $summary['now_connected'],
                'still_disconnected' => $summary['still_disconnected'],
                'errors' => $summary['errors'],
                'by_category' => $summary['by_category'],
            ],
            'results' => $items->map(fn ($item) => [
                'id' => $item->id,
                'index' => $item->sort_order,
                'domain' => $item->domain,
                'check_status' => $item->check_status,
                'connected' => $item->connected,
                'status_code' => $item->status_code,
                'probe_method' => $item->probe_method,
                'agent_version' => $item->agent_version,
                'http_status' => $item->http_status,
                'message' => $item->message,
                'attempts' => $item->attempts,
                'response_time_ms' => $item->response_time_ms,
                'category' => $item->category,
                'checked_at' => $item->checked_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Summary for disconnected recheck UI / progress.
     *
     * @return array{
     *     started_disconnected: int,
     *     now_connected: int,
     *     still_disconnected: int,
     *     errors: int,
     *     by_category: array<int, array{category: string, connected: int, disconnected: int}>
     * }
     */
    public function recheckSummary(DomainStatusCheck $check): array
    {
        $errorCodes = [
            'firewall_blocked',
            'invalid_api_key',
            'missing_api_key',
            'invalid_status_response',
            'agent_not_found',
        ];

        $errors = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', 'disconnected')
            ->whereIn('status_code', $errorCodes)
            ->count();

        $byCategory = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->whereIn('check_status', ['connected', 'disconnected'])
            ->selectRaw("
                COALESCE(NULLIF(TRIM(category), ''), 'Uncategorized') as category_name,
                SUM(CASE WHEN check_status = 'connected' THEN 1 ELSE 0 END) as connected_count,
                SUM(CASE WHEN check_status = 'disconnected' THEN 1 ELSE 0 END) as disconnected_count
            ")
            ->groupBy('category_name')
            ->orderBy('category_name')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category_name,
                'connected' => (int) $row->connected_count,
                'disconnected' => (int) $row->disconnected_count,
            ])
            ->values()
            ->all();

        return [
            'started_disconnected' => (int) $check->total_count,
            'now_connected' => (int) $check->connected_count,
            'still_disconnected' => (int) $check->disconnected_count,
            'errors' => $errors,
            'by_category' => $byCategory,
        ];
    }

    /**
     * @return \Generator<int, array<int, string|null>>
     */
    public function csvRowsForCheck(DomainStatusCheck $check): \Generator
    {
        $query = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->orderBy('sort_order');

        foreach ($query->cursor() as $item) {
            $newStatus = match ($item->check_status) {
                'connected' => 'Connected',
                'disconnected' => 'Disconnected',
                'retry_pending' => 'Verifying',
                'checking' => 'Checking',
                default => 'Pending',
            };

            yield [
                $item->domain,
                $item->category ?: 'Uncategorized',
                'Disconnected',
                $newStatus,
                $item->status_code,
                $item->message,
                $item->probe_method,
                $item->response_time_ms !== null ? (string) $item->response_time_ms : '',
                $item->checked_at?->format('Y-m-d H:i:s'),
            ];
        }
    }

    public function maxDomains(): int
    {
        return self::MAX_DOMAINS;
    }

    public function disconnectedMaxDomains(): int
    {
        return max(1, (int) config('domain_status_checker.disconnected_max', 10000));
    }

    public function maxDomainsForSource(string $source): int
    {
        return $source === 'disconnected'
            ? $this->disconnectedMaxDomains()
            : $this->maxDomains();
    }

    public function chunkSize(): int
    {
        return max(1, min(25, (int) config('domain_status_checker.chunk_size', 8)));
    }

    public function requestTimeout(): int
    {
        return max(10, (int) config('domain_status_checker.request_timeout', 60));
    }

    public function connectTimeout(): int
    {
        return max(5, (int) config('domain_status_checker.connect_timeout', 20));
    }

    public function retryRequestTimeout(): int
    {
        return max($this->requestTimeout(), (int) config('domain_status_checker.retry_request_timeout', 90));
    }

    public function retryConnectTimeout(): int
    {
        return max($this->connectTimeout(), (int) config('domain_status_checker.retry_connect_timeout', 25));
    }

    public function jobTimeoutSeconds(): int
    {
        $configured = (int) config('domain_status_checker.job_timeout', 180);

        return max($this->retryRequestTimeout() + 30, $configured);
    }

    public function lockSeconds(): int
    {
        return max($this->jobTimeoutSeconds() + 30, (int) config('domain_status_checker.lock_seconds', 240));
    }

    /**
     * Reset items left in-flight after a worker timeout or crash so they can be picked up again.
     */
    public function recoverOrphanedItems(DomainStatusCheck $check): int
    {
        $orphans = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', 'checking')
            ->get();

        $count = 0;
        foreach ($orphans as $item) {
            $attempts = (int) $item->attempts;
            $item->update([
                'check_status' => $attempts > 0 ? 'retry_pending' : 'pending',
                'next_retry_at' => $attempts > 0
                    ? ($item->next_retry_at ?? now())
                    : null,
            ]);
            $count++;
        }

        return $count;
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('domain_status_checker.max_attempts', 5));
    }

    /**
     * Delay after a failed attempt number (1-based).
     */
    public function backoffSecondsAfterAttempt(int $attemptNumber): int
    {
        $schedule = config('domain_status_checker.backoff_seconds', [60, 120, 240, 300]);
        if (! is_array($schedule) || $schedule === []) {
            $schedule = [60, 120, 240, 300];
        }

        $index = max(0, $attemptNumber - 1);
        if ($index >= count($schedule)) {
            return (int) end($schedule);
        }

        return max(1, (int) $schedule[$index]);
    }

    public function cancelCheck(DomainStatusCheck $check, string $reason = 'Cancelled by admin'): DomainStatusCheck
    {
        if ($check->isFinished()) {
            return $check;
        }

        DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->whereIn('check_status', ['pending', 'checking', 'retry_pending'])
            ->update([
                'check_status' => 'disconnected',
                'connected' => false,
                'message' => $reason,
                'next_retry_at' => null,
                'checked_at' => now(),
            ]);

        $this->recalculateCheckStats($check->fresh());

        $check->update([
            'status' => 'cancelled',
            'phase' => 'done',
            'status_message' => $reason,
            'completed_at' => now(),
        ]);

        $this->purgeQueuedJobsForCheck((int) $check->id);

        return $check->fresh();
    }

    /**
     * @return array{cancelled: int, jobs_purged: int}
     */
    public function cancelStuckChecks(?int $olderThanMinutes = null): array
    {
        $query = DomainStatusCheck::query()
            ->whereIn('status', ['queued', 'processing']);

        if ($olderThanMinutes !== null && $olderThanMinutes > 0) {
            $query->where('updated_at', '<=', now()->subMinutes($olderThanMinutes));
        }

        $cancelled = 0;
        $jobsPurged = 0;

        $query->orderBy('id')->chunkById(50, function ($checks) use (&$cancelled, &$jobsPurged) {
            foreach ($checks as $check) {
                $jobsPurged += $this->purgeQueuedJobsForCheck((int) $check->id);
                $this->cancelCheck($check, 'Cancelled (stuck leftover run)');
                $cancelled++;
            }
        });

        return [
            'cancelled' => $cancelled,
            'jobs_purged' => $jobsPurged,
        ];
    }

    public function purgeQueuedJobsForCheck(int $checkId): int
    {
        $deleted = 0;
        $needle = 's:7:"checkId";i:'.$checkId.';';

        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->orderBy('id')->get(['id', 'payload']);
            foreach ($rows as $row) {
                $payload = (string) ($row->payload ?? '');
                if (
                    str_contains($payload, 'ProcessDomainStatusCheckChunkJob')
                    && str_contains($payload, $needle)
                ) {
                    DB::table($table)->where('id', $row->id)->delete();
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DomainStatusCheckItem>
     */
    public function progressItems(DomainStatusCheck $check, ?string $since = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $check->items()
            ->orderBy('sort_order')
            ->select([
                'id',
                'domain',
                'sort_order',
                'check_status',
                'connected',
                'status_code',
                'probe_method',
                'agent_version',
                'http_status',
                'message',
                'attempts',
                'response_time_ms',
                'in_inventory',
                'category',
                'checked_at',
                'updated_at',
            ]);

        if ($since !== null && $since !== '') {
            try {
                $sinceTime = \Illuminate\Support\Carbon::parse($since);
                $query->where(function ($builder) use ($sinceTime) {
                    $builder->where('updated_at', '>', $sinceTime)
                        ->orWhere('check_status', 'checking');
                });
            } catch (\Throwable) {
                // Invalid timestamp — return full list for safety.
            }
        }

        return $query->get();
    }
}
