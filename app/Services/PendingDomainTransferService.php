<?php

namespace App\Services;

use App\Jobs\RefreshTransferredDomainsStatusJob;
use App\Models\Admin\Domain;
use App\Models\Admin\PendingDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PendingDomainTransferService
{
    private const UPSERT_CHUNK_SIZE = 200;

    /**
     * Transfer many pending domains in a single optimized operation.
     *
     * @param  array<int>  $pendingDomainIds
     * @return array{success: int, failed: int, errors: array<int, string>, domain_ids: array<int>}
     */
    public function bulkTransfer(
        array $pendingDomainIds,
        int $categoryId,
        int $adminId,
        ?string $notes = null,
        bool $queueStatusCheck = true
    ): array {
        $pendingDomains = PendingDomain::query()
            ->whereIn('id', $pendingDomainIds)
            ->where('status', 'pending')
            ->get();

        if ($pendingDomains->isEmpty()) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'domain_ids' => [],
            ];
        }

        $now = now();
        $notesText = $notes ?? 'Transferred to domains via bulk transfer';
        $errors = [];
        $approvedPendingIds = [];
        $domainRows = [];

        foreach ($pendingDomains as $pendingDomain) {
            $name = normalizeDomainName($pendingDomain->domain_name);

            if ($name === '') {
                $errors[$pendingDomain->id] = "Invalid domain name: {$pendingDomain->domain_name}";

                continue;
            }

            $approvedPendingIds[] = $pendingDomain->id;
            $domainRows[$name] = [
                'name' => $name,
                'api_key' => $pendingDomain->api_key,
                'domain_category_id' => $categoryId,
                'da' => 0,
                'dr' => 0,
                'tf' => 0,
                'ss' => 0,
                'ip' => null,
                'status' => 0,
                'admin_id' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($domainRows === []) {
            return [
                'success' => 0,
                'failed' => count($errors),
                'errors' => array_values($errors),
                'domain_ids' => [],
            ];
        }

        $transferredDomainIds = [];

        DB::transaction(function () use ($domainRows, $approvedPendingIds, $notesText, $now, &$transferredDomainIds) {
            foreach (array_chunk(array_values($domainRows), self::UPSERT_CHUNK_SIZE) as $chunk) {
                Domain::upsert(
                    $chunk,
                    ['name'],
                    ['api_key', 'domain_category_id', 'admin_id', 'status', 'updated_at']
                );
            }

            PendingDomain::query()
                ->whereIn('id', $approvedPendingIds)
                ->update([
                    'status' => 'approved',
                    'approved_at' => $now,
                    'notes' => $notesText,
                ]);

            $transferredDomainIds = Domain::query()
                ->whereIn('name', array_keys($domainRows))
                ->pluck('id')
                ->all();
        });

        if ($queueStatusCheck && $transferredDomainIds !== []) {
            RefreshTransferredDomainsStatusJob::dispatch($transferredDomainIds)
                ->onQueue('domainCheck');
        }

        Log::info('Bulk pending domain transfer completed', [
            'success' => count($approvedPendingIds),
            'failed' => count($errors),
            'category_id' => $categoryId,
            'status_check_queued' => $queueStatusCheck,
        ]);

        return [
            'success' => count($approvedPendingIds),
            'failed' => count($errors),
            'errors' => array_values($errors),
            'domain_ids' => $transferredDomainIds,
        ];
    }

    /**
     * Transfer a single pending domain (delegates to bulk transfer).
     */
    public function transfer(PendingDomain $pendingDomain, int $categoryId, int $adminId, ?string $notes = null): Domain
    {
        $result = $this->bulkTransfer([$pendingDomain->id], $categoryId, $adminId, $notes);

        if ($result['success'] === 0) {
            throw new \RuntimeException($result['errors'][0] ?? 'Transfer failed');
        }

        $name = normalizeDomainName($pendingDomain->domain_name);

        return Domain::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Check whether the remote WordPress plugin responds on the status endpoint.
     * Used for single-domain synchronous checks only.
     */
    public function checkPluginStatus(string $domainName): int
    {
        $domain = normalizeDomainName($domainName);
        if ($domain === '') {
            return 0;
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(8)
                ->connectTimeout(5)
                ->get("https://{$domain}/wp-json/external/v1/status");

            if ($response->successful() && $response->json('status') == true) {
                return 1;
            }
        } catch (\Exception $e) {
            Log::info('Plugin status check failed during domain transfer', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
        }

        return 0;
    }
}
