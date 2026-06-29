<?php

namespace App\Services;

use App\Jobs\ProcessDomainStatusCheckChunkJob;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DomainStatusCheckerService
{
    public const MAX_DOMAINS = 500;

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
        ?int $categoryId = null
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
                'domain_category_id' => $categoryId,
                'status_message' => 'Queued — waiting for queue worker (domainCheck)',
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

        $results = $this->probeDomains($items, $isRetry);

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
     * @return array<int, array{connected: bool, message: string, response_time_ms: int|null}>
     */
    public function probeDomains(Collection $items, bool $isRetry): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $timeout = $isRetry ? $this->retryRequestTimeout() : $this->requestTimeout();
        $connectTimeout = $isRetry ? $this->retryConnectTimeout() : $this->connectTimeout();

        $responses = Http::pool(function (Pool $pool) use ($items, $timeout, $connectTimeout) {
            foreach ($items as $item) {
                $pool->as((string) $item->id)
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'DiamondPBN-StatusChecker/1.0',
                    ])
                    ->get($this->statusEndpointUrl($item->domain));
            }
        });

        $results = [];

        foreach ($items as $item) {
            $response = $responses[(string) $item->id] ?? null;
            $interpreted = $this->interpretResponse($response);

            if ($response && method_exists($response, 'transferStats') && $response->transferStats) {
                $interpreted['response_time_ms'] = (int) round($response->transferStats->getTransferTime() * 1000);
            } else {
                $interpreted['response_time_ms'] = null;
            }

            $results[$item->id] = $interpreted;
        }

        return $results;
    }

    public function applyProbeResults(DomainStatusCheckItem $item, array $result, bool $isRetry): void
    {
        $connected = (bool) ($result['connected'] ?? false);
        $message = (string) ($result['message'] ?? 'Unknown response');
        $attempts = $item->attempts + 1;

        if ($connected) {
            $item->update([
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
            $item->update([
                'check_status' => 'retry_pending',
                'connected' => false,
                'message' => $message.' — queued for verification',
                'attempts' => $attempts,
                'response_time_ms' => $result['response_time_ms'] ?? null,
                'checked_at' => now(),
            ]);

            return;
        }

        $item->update([
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

    /**
     * @return array{connected: bool, message: string, http_status: int|null}
     */
    private function interpretResponse(mixed $response): array
    {
        $httpStatus = ($response && method_exists($response, 'status')) ? $response->status() : null;

        try {
            if (! $response) {
                return [
                    'connected' => false,
                    'message' => 'No response received (timeout or connection error)',
                    'http_status' => null,
                ];
            }

            if (! $response->successful()) {
                return [
                    'connected' => false,
                    'message' => $this->buildHttpErrorMessage($response),
                    'http_status' => $httpStatus,
                ];
            }

            if ($this->isPluginStatusConnected($response)) {
                return [
                    'connected' => true,
                    'message' => $this->extractApiMessage($response) ?: 'Plugin Connected',
                    'http_status' => $httpStatus,
                ];
            }

            return [
                'connected' => false,
                'message' => $this->buildPluginFalseMessage($response),
                'http_status' => $httpStatus,
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'message' => 'Request failed: '.$e->getMessage(),
                'http_status' => $httpStatus,
            ];
        }
    }

    private function statusEndpointUrl(string $domain): string
    {
        return 'https://'.normalizeDomainName($domain).'/wp-json/external/v1/status';
    }

    private function isPluginStatusConnected(mixed $response): bool
    {
        $body = $response->json();

        if (! is_array($body)) {
            return false;
        }

        $status = $body['status'] ?? ($body['data']['status'] ?? null);

        if ($status === true || $status === 1) {
            return true;
        }

        if (is_string($status)) {
            return in_array(strtolower(trim($status)), ['true', '1', 'yes', 'ok', 'connected'], true);
        }

        if (! empty($body['success']) && $this->isTruthyStatus($body['data']['status'] ?? null)) {
            return true;
        }

        return false;
    }

    private function isTruthyStatus(mixed $status): bool
    {
        if ($status === true || $status === 1) {
            return true;
        }

        if (is_string($status)) {
            return in_array(strtolower(trim($status)), ['true', '1', 'yes', 'ok', 'connected'], true);
        }

        return false;
    }

    private function extractApiMessage(mixed $response): ?string
    {
        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        $message = $body['message'] ?? ($body['data']['message'] ?? null);

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    private function buildHttpErrorMessage(mixed $response): string
    {
        $apiMessage = $this->extractApiMessage($response);
        $code = $response->json('code');
        $status = $response->status();

        if ($apiMessage && $code) {
            return "HTTP {$status} ({$code}): {$apiMessage}";
        }

        if ($apiMessage) {
            return "HTTP {$status}: {$apiMessage}";
        }

        return match ($status) {
            404 => 'HTTP 404: Plugin Missing / API Route Not Found',
            403 => 'HTTP 403: Access forbidden (firewall or security plugin)',
            500, 502, 503 => "HTTP {$status}: Remote server error",
            default => "HTTP {$status}",
        };
    }

    private function buildPluginFalseMessage(mixed $response): string
    {
        $apiMessage = $this->extractApiMessage($response);
        $body = $response->json();
        $rawStatus = is_array($body) ? ($body['status'] ?? 'missing') : 'invalid-json';

        if ($apiMessage) {
            return $apiMessage;
        }

        return 'Plugin not connected (API returned status='.json_encode($rawStatus).')';
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
