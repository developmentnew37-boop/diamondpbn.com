<?php

namespace App\Services;

use App\Jobs\ProcessDomainStatusCheckChunkJob;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Process the next chunk for an in-progress check (queue worker only).
     */
    public function processNextChunk(DomainStatusCheck $check): void
    {
        if ($check->isFinished()) {
            return;
        }

        $this->recoverOrphanedItems($check);

        $phase = $check->phase === 'retry' ? 'retry' : 'initial';
        $isRetry = $phase === 'retry';
        $pendingStatus = $isRetry ? 'retry_pending' : 'pending';

        if (in_array($check->status, ['queued', 'processing'], true)) {
            $check->update([
                'status' => 'processing',
                'phase' => $phase,
                'started_at' => $check->started_at ?? now(),
                'status_message' => $isRetry
                    ? 'Verifying disconnected domains (2nd pass)...'
                    : 'Checking domains...',
            ]);
        }

        $items = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', $pendingStatus)
            ->orderBy('sort_order')
            ->limit($this->chunkSize())
            ->get();

        if ($items->isEmpty()) {
            $this->advancePhase($check, $phase);

            return;
        }

        DomainStatusCheckItem::query()
            ->whereIn('id', $items->pluck('id'))
            ->update(['check_status' => 'checking']);

        $results = $this->probeDomains($items, $isRetry, (bool) $check->use_authenticated_check);

        foreach ($items as $item) {
            $this->applyProbeResults($item, $results[$item->id] ?? [
                'connected' => false,
                'message' => 'No response received',
                'response_time_ms' => null,
            ], $isRetry);
        }

        $check = $check->fresh();
        $this->recalculateCheckStats($check);

        // Finalize or advance phase in the same job when this batch was the last
        // (avoids relying on a follow-up empty-chunk job that may never run).
        $remainingInPhase = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', $pendingStatus)
            ->exists();

        if (! $remainingInPhase) {
            $this->advancePhase($check->fresh(), $phase);
        }
    }

    private function advancePhase(DomainStatusCheck $check, string $phase): void
    {
        if ($phase === 'initial') {
            $retryCount = DomainStatusCheckItem::query()
                ->where('domain_status_check_id', $check->id)
                ->where('check_status', 'retry_pending')
                ->count();

            if ($retryCount > 0) {
                $check->update([
                    'phase' => 'retry',
                    'status_message' => "Verifying {$retryCount} domain(s) marked disconnected...",
                ]);

                return;
            }

            $this->finalizeCheck($check);

            return;
        }

        $this->finalizeCheck($check);
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

        // First-pass complete + finalized items drive the progress bar.
        $firstPassDone = $processed + $retryPending;
        $progressPercent = (int) min(100, round(($firstPassDone / $total) * 100));

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
    public function probeDomains(Collection $items, bool $isRetry, bool $useAuthenticatedCheck = false): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $timeout = $isRetry ? $this->retryRequestTimeout() : $this->requestTimeout();
        $connectTimeout = $isRetry ? $this->retryConnectTimeout() : $this->connectTimeout();

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

    public function applyProbeResults(DomainStatusCheckItem $item, array $result, bool $isRetry): void
    {
        $connected = (bool) ($result['connected'] ?? false);
        $message = (string) ($result['message'] ?? 'Unknown response');
        $attempts = $item->attempts + 1;
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
                'message' => $isRetry ? 'Connected (verified on 2nd check)' : $message,
                'attempts' => $attempts,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'checked_at' => now(),
            ]);

            return;
        }

        if (! $isRetry) {
            $item->update($classification + [
                'check_status' => 'retry_pending',
                'connected' => false,
                'message' => $message.' — queued for verification',
                'attempts' => $attempts,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'checked_at' => now(),
            ]);

            return;
        }

        $item->update($classification + [
            'check_status' => 'disconnected',
            'connected' => false,
            'message' => $isRetry
                ? $message.' (still failing after 2nd check)'
                : $message,
            'attempts' => $attempts,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'checked_at' => now(),
        ]);
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

    public function maxDomains(): int
    {
        return self::MAX_DOMAINS;
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
        $isRetry = $check->phase === 'retry';
        $pendingStatus = $isRetry ? 'retry_pending' : 'pending';

        return DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->where('check_status', 'checking')
            ->update(['check_status' => $pendingStatus]);
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
