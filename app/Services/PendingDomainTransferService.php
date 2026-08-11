<?php

namespace App\Services;

use App\Jobs\RefreshTransferredDomainsStatusJob;
use App\Models\Admin\Domain;
use App\Models\Admin\PendingDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PendingDomainTransferService
{
    public function __construct(
        private readonly WordPressAgentStatusService $agentStatusService
    ) {}

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
            foreach ($domainRows as $row) {
                $domain = Domain::query()->firstOrNew(['name' => $row['name']]);
                $domain->fill([
                    'api_key' => $row['api_key'],
                    'domain_category_id' => $row['domain_category_id'],
                    'admin_id' => $row['admin_id'],
                    'status' => $row['status'],
                ]);

                if (! $domain->exists) {
                    $domain->fill([
                        'da' => $row['da'],
                        'dr' => $row['dr'],
                        'tf' => $row['tf'],
                        'ss' => $row['ss'],
                        'ip' => $row['ip'],
                    ]);
                }

                $domain->save();
            }

            PendingDomain::query()
                ->whereIn('id', $approvedPendingIds)
                ->update([
                    'status' => 'approved',
                    'approved_at' => $now,
                    'notes' => $notesText,
                ]);

            $transferredDomainIds = Domain::query()
                ->whereNormalizedNameIn(array_keys($domainRows))
                ->pluck('id')
                ->all();
        });

        if ($queueStatusCheck && $transferredDomainIds !== []) {
            RefreshTransferredDomainsStatusJob::dispatch($transferredDomainIds)
                ->onQueue((string) config('domain_status_checker.health_sync_queue', 'domainHealthSync'));
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
     * Sync all pending domains that already exist in the domains inventory.
     * Updates API key from webhook submission; preserves category, metrics, and status.
     * Marks matching pending rows as approved (removed from pending list).
     *
     * @return array{synced: int, skipped_new: int, failed: int, errors: array<int, string>, domain_ids: array<int>}
     */
    public function syncExistingPendingDomains(
        int $adminId,
        ?string $notes = null,
        bool $queueStatusCheck = true,
        ?array $onlyPendingIds = null
    ): array {
        $query = PendingDomain::query()
            ->where('status', 'pending')
            ->orderBy('id');

        if ($onlyPendingIds !== null && $onlyPendingIds !== []) {
            $query->whereIn('id', $onlyPendingIds);
        }

        $pendingDomains = $query->get();

        if ($pendingDomains->isEmpty()) {
            return [
                'synced' => 0,
                'skipped_new' => 0,
                'failed' => 0,
                'errors' => [],
                'domain_ids' => [],
            ];
        }

        $normalizedNames = [];
        foreach ($pendingDomains as $pendingDomain) {
            $name = normalizeDomainName($pendingDomain->domain_name);
            if ($name !== '') {
                $normalizedNames[$pendingDomain->id] = $name;
            }
        }

        $existingByName = Domain::collectionByNormalizedName(array_values($normalizedNames));

        $notesText = $notes ?? 'Synced API key with existing domain in inventory';
        $now = now();
        $errors = [];
        $approvedPendingIds = [];
        $updatedDomainIds = [];
        $skippedNew = 0;

        DB::transaction(function () use (
            $pendingDomains,
            $normalizedNames,
            $existingByName,
            $notesText,
            $now,
            &$errors,
            &$approvedPendingIds,
            &$updatedDomainIds,
            &$skippedNew
        ) {
            foreach ($pendingDomains as $pendingDomain) {
                $name = $normalizedNames[$pendingDomain->id] ?? '';

                if ($name === '') {
                    $errors[$pendingDomain->id] = "Invalid domain name: {$pendingDomain->domain_name}";

                    continue;
                }

                $existing = $existingByName->get($name);

                if ($existing === null) {
                    $skippedNew++;

                    continue;
                }

                $existing->update([
                    'name' => $name,
                    'api_key' => $pendingDomain->api_key,
                    'updated_at' => $now,
                ]);

                $approvedPendingIds[] = $pendingDomain->id;
                $updatedDomainIds[] = $existing->id;
            }

            if ($approvedPendingIds !== []) {
                PendingDomain::query()
                    ->whereIn('id', $approvedPendingIds)
                    ->update([
                        'status' => 'approved',
                        'approved_at' => $now,
                        'notes' => $notesText,
                    ]);
            }
        });

        $updatedDomainIds = array_values(array_unique($updatedDomainIds));

        if ($queueStatusCheck && $updatedDomainIds !== []) {
            RefreshTransferredDomainsStatusJob::dispatch($updatedDomainIds)
                ->onQueue((string) config('domain_status_checker.health_sync_queue', 'domainHealthSync'));
        }

        Log::info('Pending domain inventory sync completed', [
            'synced' => count($approvedPendingIds),
            'skipped_new' => $skippedNew,
            'failed' => count($errors),
            'status_check_queued' => $queueStatusCheck,
            'admin_id' => $adminId,
        ]);

        return [
            'synced' => count($approvedPendingIds),
            'skipped_new' => $skippedNew,
            'failed' => count($errors),
            'errors' => array_values($errors),
            'domain_ids' => $updatedDomainIds,
        ];
    }

    /**
     * Count pending domains whose hostname already exists in the domains inventory.
     */
    public function countPendingExistingInInventory(): int
    {
        return count($this->pendingNamesExistingInInventory());
    }

    /**
     * Normalized hostnames from pending list that already exist in domains inventory.
     *
     * @return array<string, true>
     */
    public function pendingNamesExistingInInventory(): array
    {
        $pendingNames = PendingDomain::query()
            ->where('status', 'pending')
            ->pluck('domain_name')
            ->map(fn ($name) => normalizeDomainName($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($pendingNames === []) {
            return [];
        }

        return Domain::query()
            ->whereNormalizedNameIn($pendingNames)
            ->get()
            ->mapWithKeys(fn (Domain $domain) => [normalizeDomainName($domain->name) => true])
            ->all();
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

        return Domain::findByNormalizedName($pendingDomain->domain_name)
            ?? throw new \RuntimeException('Domain not found after transfer');
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

        $domainModel = Domain::findByNormalizedName($domain);
        $apiKey = '';

        if ($domainModel !== null) {
            try {
                $apiKey = trim((string) ($domainModel->api_key ?? ''));
            } catch (\Throwable) {
                $apiKey = '';
            }
        }

        $result = $this->agentStatusService->probeWithAuthFallback($domain, $apiKey);
        $domainModel?->persistAgentHealth($result);

        return $result->ok ? 1 : 0;
    }
}
