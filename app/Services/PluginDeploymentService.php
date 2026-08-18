<?php

namespace App\Services;

use App\Jobs\ProcessPluginDeploymentChunkJob;
use App\Models\Admin\Domain;
use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginDeploymentItem;
use App\Models\Admin\PluginPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PluginDeploymentService
{
    public function __construct(
        private readonly DomainStatusCheckerService $domainStatusChecker,
        private readonly RemotePluginManagerService $remotePluginManager,
        private readonly PluginPackageService $pluginPackageService,
        private readonly PluginDeployPreflightService $preflight,
    ) {}

    /**
     * @return array<int, string>
     */
    public function parseDomainList(string $input): array
    {
        return $this->domainStatusChecker->parseDomainList($input);
    }

    /**
     * @return array{names: array<int, string>, total_in_scope: int}
     */
    public function getDomainNamesFromInventory(?int $categoryId = null): array
    {
        $max = $this->maxDomains();
        $query = Domain::query()->orderBy('name');

        if ($categoryId !== null) {
            $query->where('domain_category_id', $categoryId);
        }

        $totalInScope = (clone $query)->count();
        $names = $query->limit($max)->pluck('name')->all();

        return ['names' => $names, 'total_in_scope' => $totalInScope];
    }

    public function maxDomains(): int
    {
        return max(1, (int) config('plugin_manager.max_domains_per_deployment', 500));
    }

    /**
     * @param  array<int, string>  $domainNames
     */
    public function createDeployment(
        int $adminId,
        PluginPackage $package,
        string $operation,
        array $domainNames,
        string $source,
        ?int $categoryId,
        bool $activateAfter,
        bool $skipIfSameVersion
    ): PluginDeployment {
        if (! in_array($operation, PluginDeployment::OPERATIONS, true)) {
            throw new \InvalidArgumentException('Invalid deployment operation.');
        }

        $inventoryByName = Domain::query()
            ->with('domainCategory:id,name')
            ->whereIn('name', $domainNames)
            ->get()
            ->keyBy('name');

        $deployment = DB::transaction(function () use (
            $adminId,
            $package,
            $operation,
            $domainNames,
            $source,
            $categoryId,
            $activateAfter,
            $skipIfSameVersion,
            $inventoryByName
        ) {
            $deployment = PluginDeployment::create([
                'admin_id' => $adminId,
                'plugin_package_id' => $package->id,
                'operation' => $operation,
                'source' => $source,
                'domain_category_id' => $categoryId,
                'status' => 'queued',
                'phase' => 'initial',
                'total_count' => count($domainNames),
                'activate_after' => $activateAfter,
                'skip_if_same_version' => $skipIfSameVersion,
                'status_message' => 'Queued — waiting for queue worker (plugin_deployments)',
            ]);

            $rows = [];
            $now = now();

            foreach ($domainNames as $index => $domainName) {
                $inventoryDomain = $inventoryByName->get($domainName);

                $rows[] = [
                    'plugin_deployment_id' => $deployment->id,
                    'domain' => $domainName,
                    'sort_order' => $index + 1,
                    'item_status' => 'pending',
                    'in_inventory' => $inventoryDomain !== null,
                    'domain_id' => $inventoryDomain?->id,
                    'category' => $inventoryDomain?->domainCategory?->name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                PluginDeploymentItem::insert($chunk);
            }

            ProcessPluginDeploymentChunkJob::dispatch($deployment->id)
                ->onQueue('plugin_deployments');

            return $deployment;
        });

        return $deployment;
    }

    public function processNextChunk(PluginDeployment $deployment): void
    {
        if ($deployment->isFinished()) {
            return;
        }

        $deployment->refresh();
        if ($deployment->isCancelled()) {
            return;
        }

        $this->recoverOrphanedItems($deployment);

        if (in_array($deployment->status, ['queued', 'running'], true)) {
            $deployment->update([
                'status' => 'running',
                'started_at' => $deployment->started_at ?? now(),
                'status_message' => 'Deploying plugin to remote sites...',
            ]);
        }

        $items = PluginDeploymentItem::query()
            ->where('plugin_deployment_id', $deployment->id)
            ->where('item_status', 'pending')
            ->orderBy('sort_order')
            ->limit($this->chunkSize())
            ->get();

        if ($items->isEmpty()) {
            $this->finalizeDeployment($deployment->fresh());

            return;
        }

        PluginDeploymentItem::query()
            ->whereIn('id', $items->pluck('id'))
            ->update(['item_status' => 'processing']);

        $package = $deployment->pluginPackage;
        if (! $package) {
            $deployment->update([
                'status' => 'failed',
                'status_message' => 'Plugin package missing',
                'completed_at' => now(),
            ]);

            return;
        }

        $downloadUrl = $this->pluginPackageService->signedDownloadUrl($package, $deployment);

        foreach ($items as $item) {
            if ($deployment->fresh()?->isCancelled()) {
                break;
            }

            $this->processItem($deployment, $package, $item, $downloadUrl);
        }

        $this->recalculateStats($deployment->fresh());

        if (! $deployment->fresh()->isFinished() && ! $deployment->fresh()->isCancelled()) {
            $remaining = PluginDeploymentItem::query()
                ->where('plugin_deployment_id', $deployment->id)
                ->where('item_status', 'pending')
                ->exists();

            if (! $remaining) {
                $this->finalizeDeployment($deployment->fresh());
            }
        }
    }

    private function processItem(
        PluginDeployment $deployment,
        PluginPackage $package,
        PluginDeploymentItem $item,
        string $downloadUrl
    ): void {
        $domain = Domain::query()->find($item->domain_id);

        if (! $domain || empty($domain->api_key)) {
            $this->markSkipped($item, 'not_in_inventory', 'Domain not in inventory or missing API key');

            return;
        }

        $agentStatus = $this->remotePluginManager->fetchAgentStatus($domain);
        $auditTrail = [$this->remotePluginManager->agentStatusAudit($domain, $agentStatus)];

        if (! $agentStatus['ok']) {
            $this->markSkipped(
                $item,
                (string) ($agentStatus['code'] ?? 'domain_offline'),
                (string) ($agentStatus['message'] ?? 'Remote agent status check failed'),
                metadata: [
                    'http_status' => $agentStatus['http_status'] ?? null,
                    'probe_method' => $agentStatus['probe_method'] ?? 'status',
                    'response_time_ms' => $agentStatus['response_time_ms'] ?? null,
                    'audit_trail' => $auditTrail,
                ]
            );

            return;
        }

        if (! $agentStatus['plugin_manager_supported']) {
            $minVersion = (string) config('plugin_manager.min_agent_version', '8.1.5');
            $remoteVersion = $agentStatus['plugin_version'] ?? 'unknown';
            $code = (string) ($agentStatus['code'] ?? 'agent_unsupported');

            $this->markSkipped(
                $item,
                $code,
                $code === 'agent_outdated'
                    ? "Remote agent outdated ({$remoteVersion}); requires Diamond PBN ≥ {$minVersion} with plugin manager API"
                    : "Remote agent does not support plugin management ({$remoteVersion}); requires Diamond PBN ≥ {$minVersion}",
                metadata: [
                    'http_status' => $agentStatus['http_status'] ?? null,
                    'probe_method' => $agentStatus['probe_method'] ?? 'status',
                    'response_time_ms' => $agentStatus['response_time_ms'] ?? null,
                    'audit_trail' => $auditTrail,
                ]
            );

            return;
        }

        $inventoryResult = $this->remotePluginManager->fetchPluginsInventory($domain);
        $auditTrail = array_merge($auditTrail, $inventoryResult['audit'] ?? []);
        $inventory = $inventoryResult['ok'] ? $inventoryResult['plugins'] : null;

        if (! $inventoryResult['ok']) {
            if ($this->requiresInventory()) {
                $this->markInventoryUnavailable($item, $inventoryResult, $auditTrail);

                return;
            }

            $pluginInfo = $this->remotePluginManager->fetchPluginInfo($domain, $package);
            $auditTrail = array_merge($auditTrail, $pluginInfo['audit'] ?? []);

            if ($pluginInfo['ok'] && $pluginInfo['found'] === true) {
                $inventory = [[
                    'slug' => $package->expectedSlug(),
                    'version' => $pluginInfo['version'],
                    'plugin_file' => $pluginInfo['plugin_file'],
                    'active' => $pluginInfo['active'],
                ]];
            } elseif ($pluginInfo['ok'] && $pluginInfo['found'] === false && $pluginInfo['http_status'] === 404) {
                $inventory = [];
            } else {
                $this->markInventoryUnavailable($item, $pluginInfo, $auditTrail);

                return;
            }
        }

        /** @var array<int, array<string, mixed>> $inventory */
        $installedMatch = $this->preflight->findInstalledMatch($inventory, $package);
        $folderMatch = $this->preflight->findFolderMatch($inventory, $package);
        $exactMatch = $this->preflight->findExactVersionMatch($inventory, $package);
        $versionBefore = $exactMatch['version'] ?? $installedMatch['version'] ?? $folderMatch['version'] ?? null;
        $pluginFileHint = $exactMatch['plugin_file'] ?? $installedMatch['plugin_file'] ?? $folderMatch['plugin_file'] ?? null;

        $intent = $deployment->operation;
        $operation = $this->preflight->resolveOperation($intent, $inventory, $package);

        // Activate/deactivate/delete: inventory miss is often a field/slug shape issue.
        // Re-check via single-plugin lookup, then still attempt remote so the agent can resolve.
        if ($operation === 'skip' && in_array($intent, ['activate', 'deactivate', 'delete'], true)) {
            $resolved = $this->resolveMutateWhenInventoryMisses(
                $domain,
                $package,
                $inventory,
                $auditTrail
            );
            $auditTrail = $resolved['audit'];

            if ($resolved['match'] !== null) {
                $installedMatch = $resolved['match'];
                $versionBefore = $installedMatch['version'] ?? $versionBefore;
                $pluginFileHint = $installedMatch['plugin_file'] ?? $pluginFileHint;
                $operation = $intent;
            } elseif ($resolved['verified_missing']) {
                $this->markSkipped(
                    $item,
                    'plugin_not_found',
                    $this->mutateSkipMessage($intent, $package, $inventory),
                    $versionBefore,
                    null,
                    $this->resultMetadata($inventoryResult, $auditTrail)
                );

                return;
            } else {
                // Inconclusive inventory match — let the agent resolve by expected_slug / library slug.
                $operation = $intent;
            }
        }

        if ($operation === 'skip') {
            $message = match ($intent) {
                'delete', 'activate', 'deactivate' => $this->mutateSkipMessage($intent, $package, $inventory),
                default => $exactMatch !== null
                    ? 'Already at target version '.$package->version
                    : 'Nothing to do for this package on site',
            };

            $this->markSkipped(
                $item,
                $exactMatch !== null ? 'already_current' : 'plugin_not_found',
                $message,
                $versionBefore,
                $exactMatch !== null ? $versionBefore : null,
                $this->resultMetadata($inventoryResult, $auditTrail)
            );

            return;
        }

        if (in_array($operation, ['install', 'update'], true)) {
            if ($deployment->skip_if_same_version && $exactMatch !== null) {
                $this->markSkipped(
                    $item,
                    'already_current',
                    'Already on version '.$package->version,
                    $versionBefore,
                    $versionBefore,
                    $this->resultMetadata($inventoryResult, $auditTrail)
                );

                return;
            }

            if ($operation === 'update' && $versionBefore !== null
                && $deployment->skip_if_same_version
                && version_compare($versionBefore, $package->version, '>=')) {
                $this->markSkipped(
                    $item,
                    'already_current',
                    'Already on version '.$versionBefore,
                    $versionBefore,
                    $versionBefore,
                    $this->resultMetadata($inventoryResult, $auditTrail)
                );

                return;
            }

            if ($intent === 'update_if_older' && $versionBefore !== null
                && version_compare($versionBefore, $package->version, '>')) {
                $this->markSkipped(
                    $item,
                    'already_newer',
                    'Remote version '.$versionBefore.' is newer than package',
                    $versionBefore,
                    $versionBefore,
                    $this->resultMetadata($inventoryResult, $auditTrail)
                );

                return;
            }

        }

        $result = $this->executeRemoteOperation(
            $domain,
            $package,
            $operation,
            $downloadUrl,
            $deployment->activate_after,
            $pluginFileHint,
            $versionBefore
        );
        $auditTrail = array_merge($auditTrail, $result['audit'] ?? []);
        $correctedOperation = false;

        if (! $result['success'] && $operation === 'install' && $result['error_code'] === 'plugin_already_installed') {
            $result = $this->executeRemoteOperation(
                $domain,
                $package,
                'update',
                $downloadUrl,
                $deployment->activate_after,
                $pluginFileHint,
                $versionBefore
            );
            $operation = 'update';
            $correctedOperation = true;
            $auditTrail = array_merge($auditTrail, $result['audit'] ?? []);
        }

        if (! $correctedOperation
            && ! $result['success']
            && $operation === 'update'
            && $this->shouldCorrectUpdateToInstall($result)) {
            $result = $this->executeRemoteOperation(
                $domain,
                $package,
                'install',
                $downloadUrl,
                $deployment->activate_after,
                $pluginFileHint,
                $versionBefore
            );
            $operation = 'install';
            $correctedOperation = true;
            $auditTrail = array_merge($auditTrail, $result['audit'] ?? []);
        }

        if (! $result['success'] && $result['error_code'] === 'ambiguous_plugin') {
            $ambiguousResult = $this->retryAmbiguousOperation(
                $domain,
                $package,
                $operation,
                $downloadUrl,
                in_array($operation, ['install', 'update'], true) && $deployment->activate_after,
                $result,
                $pluginFileHint,
                $versionBefore
            );
            if ($ambiguousResult !== $result) {
                $auditTrail = array_merge($auditTrail, $ambiguousResult['audit'] ?? []);
            }
            $result = $ambiguousResult;
        }

        $responseMs = $result['response_time_ms'] ?? null;

        if ($result['success']) {
            $data = $result['data'];
            $versionAfter = isset($data['new_version']) ? (string) $data['new_version'] : ($operation === 'delete' ? null : $package->version);
            $action = (string) ($data['action'] ?? $operation);
            $pluginFile = isset($data['plugin_file']) ? (string) $data['plugin_file'] : $pluginFileHint;
            $resolvedVia = isset($data['resolved_via']) ? (string) $data['resolved_via'] : null;

            if ($action === 'skipped') {
                $this->markSkipped(
                    $item,
                    'already_current',
                    $result['message'] ?: 'Already at target version',
                    $data['previous_version'] ?? $versionBefore,
                    $data['new_version'] ?? $versionBefore,
                    $this->resultMetadata($result, $auditTrail)
                );

                return;
            }

            $item->update([
                'item_status' => 'success',
                'operation_result' => $action,
                'version_before' => $data['previous_version'] ?? $versionBefore,
                'version_after' => $versionAfter,
                'plugin_file' => $pluginFile,
                'resolved_via' => $resolvedVia,
                'message' => $result['message'],
                'attempts' => $item->attempts + 1,
                'response_time_ms' => $responseMs,
                ...$this->resultMetadata($result, $auditTrail),
                'processed_at' => now(),
            ]);

            return;
        }

        $item->update([
            'item_status' => 'failed',
            'operation_result' => 'failed',
            'version_before' => $versionBefore,
            'plugin_file' => $pluginFileHint,
            'error_code' => $result['error_code'] ?? 'install_failed',
            'message' => $this->humanizeDeployError($result['error_code'] ?? null, $result['message'], $package),
            'attempts' => $item->attempts + 1,
            'response_time_ms' => $responseMs,
            ...$this->resultMetadata($result, $auditTrail),
            'processed_at' => now(),
        ]);
    }

    /**
     * @param  array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms?: int}  $result
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms?: int}
     */
    private function retryAmbiguousOperation(
        Domain $domain,
        PluginPackage $package,
        string $operation,
        string $downloadUrl,
        bool $activateAfter,
        array $result,
        ?string $pluginFileHint,
        ?string $remoteVersion = null
    ): array {
        $candidates = is_array($result['data']['candidates'] ?? null) ? $result['data']['candidates'] : [];
        $candidate = $this->preflight->pickCandidateForVersion($candidates, $package->version);

        // Delete/activate/deactivate should target the installed copy, not the library package version.
        if ($candidate === null && in_array($operation, ['delete', 'activate', 'deactivate'], true)) {
            $candidate = $this->preflight->pickSingleCandidate($candidates);
        }

        $pluginFile = $candidate['plugin_file'] ?? $pluginFileHint;
        $candidateVersion = isset($candidate['version']) ? (string) $candidate['version'] : $remoteVersion;

        if ($candidate !== null && ! empty($pluginFile)) {
            return $this->executeRemoteOperation(
                $domain,
                $package,
                $operation,
                $downloadUrl,
                $activateAfter,
                (string) $pluginFile,
                $candidateVersion
            );
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     * @param  array<int, array<string, mixed>>  $auditTrail
     * @return array{
     *     match: array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null,
     *     verified_missing: bool,
     *     audit: array<int, array<string, mixed>>
     * }
     */
    private function resolveMutateWhenInventoryMisses(
        Domain $domain,
        PluginPackage $package,
        array $inventory,
        array $auditTrail
    ): array {
        $pluginInfo = $this->remotePluginManager->fetchPluginInfo($domain, $package);
        $auditTrail = array_merge($auditTrail, $pluginInfo['audit'] ?? []);

        if ($pluginInfo['ok'] && $pluginInfo['found'] === true) {
            return [
                'match' => [
                    'slug' => $package->expectedSlug(),
                    'version' => isset($pluginInfo['version']) ? (string) $pluginInfo['version'] : null,
                    'plugin_file' => isset($pluginInfo['plugin_file']) ? (string) $pluginInfo['plugin_file'] : null,
                    'active' => isset($pluginInfo['active']) ? (bool) $pluginInfo['active'] : null,
                ],
                'verified_missing' => false,
                'audit' => $auditTrail,
            ];
        }

        if ($pluginInfo['ok'] && $pluginInfo['found'] === false && (int) ($pluginInfo['http_status'] ?? 0) === 404) {
            return [
                'match' => null,
                'verified_missing' => true,
                'audit' => $auditTrail,
            ];
        }

        return [
            'match' => null,
            'verified_missing' => false,
            'audit' => $auditTrail,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     */
    private function mutateSkipMessage(string $intent, PluginPackage $package, array $inventory): string
    {
        $base = match ($intent) {
            'delete' => 'Plugin not on site for folder "'.$package->expectedSlug()
                .'" — already removed, or the installed folder name differs from this package',
            default => 'Plugin not installed on site for folder "'.$package->expectedSlug().'"',
        };

        $folders = $this->preflight->inventoryFolderLabels($inventory);
        if ($folders === []) {
            return $base.' (inventory listed no plugin folders)';
        }

        return $base.' (inventory folders: '.implode(', ', $folders).')';
    }

    /**
     * Remote update often returns not_found / bare 404 / "Plugin is not installed."
     * Treat those as missing-plugin so we can fall back to install (upgrade path).
     *
     * @param  array{error_code?: ?string, message?: string}  $result
     */
    private function shouldCorrectUpdateToInstall(array $result): bool
    {
        $code = (string) ($result['error_code'] ?? '');
        if (in_array($code, ['plugin_not_found', 'not_found', 'endpoint_not_found'], true)) {
            return true;
        }

        $message = (string) ($result['message'] ?? '');

        return $message !== '' && preg_match('/not\s+installed/i', $message) === 1;
    }

    private function humanizeDeployError(?string $errorCode, string $message, PluginPackage $package): string
    {
        return match ($errorCode) {
            'slug_mismatch' => 'ZIP folder mismatch: expected WordPress folder "'
                .$package->expectedSlug().'" but remote rejected the slug. Re-upload the package or fix expected_slug.',
            'checksum_mismatch' => 'Downloaded ZIP checksum did not match. Re-upload the package to the library.',
            'invalid_download_host' => 'Signed download URL domain is not allowlisted on the WordPress site.',
            'protected_plugin' => 'Cannot delete or deactivate the protected Diamond PBN agent plugin.',
            'filesystem_not_writable' => 'Remote filesystem is not writable. Check hosting permissions.',
            'agent_outdated' => 'Remote Diamond PBN agent is too old for plugin manager deploys.',
            'ambiguous_plugin' => 'Multiple plugin versions match on the remote site. '.$message,
            default => $message !== '' ? $message : 'Deploy operation failed.',
        };
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms?: int}
     */
    private function executeRemoteOperation(
        Domain $domain,
        PluginPackage $package,
        string $operation,
        string $downloadUrl,
        bool $activateAfter,
        ?string $pluginFileHint,
        ?string $remoteVersion = null
    ): array {
        return match ($operation) {
            'install' => $this->remotePluginManager->installOrUpdate(
                $domain,
                'install',
                $package,
                $downloadUrl,
                $activateAfter,
                $pluginFileHint
            ),
            'update' => $this->remotePluginManager->installOrUpdate(
                $domain,
                'update',
                $package,
                $downloadUrl,
                $activateAfter,
                $pluginFileHint
            ),
            'activate' => $this->remotePluginManager->activate($domain, $package, $remoteVersion),
            'deactivate' => $this->remotePluginManager->deactivate($domain, $package, $remoteVersion),
            'delete' => $this->remotePluginManager->deletePlugin(
                $domain,
                $package,
                $pluginFileHint,
                $remoteVersion
            ),
            default => [
                'success' => false,
                'message' => 'Unknown operation',
                'error_code' => 'invalid_operation',
                'data' => [],
                'response_time_ms' => 0,
                'http_status' => null,
                'request_url' => null,
                'probe_method' => 'action:unknown',
                'retry_count' => 0,
                'audit' => [],
            ],
        };
    }

    private function markSkipped(
        PluginDeploymentItem $item,
        string $errorCode,
        string $message,
        ?string $versionBefore = null,
        ?string $versionAfter = null,
        array $metadata = []
    ): void {
        $item->update([
            'item_status' => 'skipped',
            'operation_result' => 'skipped',
            'error_code' => $errorCode,
            'message' => $message,
            'version_before' => $versionBefore,
            'version_after' => $versionAfter,
            'attempts' => $item->attempts + 1,
            ...$metadata,
            'processed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $result */
    private function markInventoryUnavailable(
        PluginDeploymentItem $item,
        array $result,
        array $auditTrail
    ): void {
        $item->update([
            'item_status' => 'failed',
            'operation_result' => 'failed',
            'error_code' => 'inventory_unavailable',
            'message' => 'Plugin inventory could not be verified; no remote change was attempted. '
                .((string) ($result['message'] ?? 'Inventory request failed')),
            'attempts' => $item->attempts + 1,
            ...$this->resultMetadata($result, $auditTrail),
            'processed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $result */
    private function resultMetadata(array $result, array $auditTrail): array
    {
        $lastAudit = $auditTrail !== [] ? $auditTrail[array_key_last($auditTrail)] : [];

        return [
            'http_status' => $result['http_status'] ?? ($lastAudit['http_status'] ?? null),
            'request_url' => $result['request_url'] ?? ($lastAudit['request_url'] ?? null),
            'probe_method' => $result['probe_method'] ?? ($lastAudit['probe_method'] ?? null),
            'retry_count' => (int) ($result['retry_count'] ?? 0),
            'audit_trail' => $auditTrail,
            'response_time_ms' => $result['response_time_ms'] ?? ($lastAudit['response_time_ms'] ?? null),
        ];
    }

    private function requiresInventory(): bool
    {
        return (bool) config(
            'plugin_manager.require_inventory',
            config('plugin_manager.require_inventory_match', true)
        );
    }

    public function recalculateStats(PluginDeployment $deployment): void
    {
        $stats = PluginDeploymentItem::query()
            ->where('plugin_deployment_id', $deployment->id)
            ->selectRaw("
                SUM(CASE WHEN item_status IN ('success', 'failed', 'skipped') THEN 1 ELSE 0 END) as processed_count,
                SUM(CASE WHEN item_status = 'success' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN item_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN item_status = 'skipped' THEN 1 ELSE 0 END) as skipped_count
            ")
            ->first();

        $deployment->update([
            'processed_count' => (int) ($stats->processed_count ?? 0),
            'success_count' => (int) ($stats->success_count ?? 0),
            'failed_count' => (int) ($stats->failed_count ?? 0),
            'skipped_count' => (int) ($stats->skipped_count ?? 0),
        ]);
    }

    public function finalizeDeployment(PluginDeployment $deployment): void
    {
        $failed = $deployment->failed_count;
        $message = $failed > 0
            ? "Completed with {$failed} failure(s)"
            : 'Deployment completed';

        $deployment->update([
            'status' => 'completed',
            'phase' => 'done',
            'status_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function cancelDeployment(PluginDeployment $deployment): void
    {
        if ($deployment->isFinished()) {
            return;
        }

        PluginDeploymentItem::query()
            ->where('plugin_deployment_id', $deployment->id)
            ->where('item_status', 'pending')
            ->update([
                'item_status' => 'skipped',
                'operation_result' => 'skipped',
                'error_code' => 'cancelled',
                'message' => 'Deployment cancelled by admin',
                'processed_at' => now(),
            ]);

        $this->recalculateStats($deployment->fresh());

        $deployment->update([
            'status' => 'cancelled',
            'phase' => 'done',
            'status_message' => 'Cancelled by admin',
            'completed_at' => now(),
        ]);
    }

    public function deleteDeployment(PluginDeployment $deployment): void
    {
        if ($deployment->isActive()) {
            $this->cancelDeployment($deployment);
            $deployment->refresh();
        }

        $deployment->delete();
    }

    /**
     * @param  array<int, string>  $uuids
     * @return array{deleted: int, cancelled: int, not_found: int}
     */
    public function bulkDeleteDeployments(array $uuids, int $adminId): array
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        $deleted = 0;
        $cancelled = 0;

        $deployments = PluginDeployment::query()
            ->where('admin_id', $adminId)
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $uuid) {
            $deployment = $deployments->get($uuid);
            if (! $deployment) {
                continue;
            }

            if ($deployment->isActive()) {
                $cancelled++;
            }

            $this->deleteDeployment($deployment);
            $deleted++;
        }

        return [
            'deleted' => $deleted,
            'cancelled' => $cancelled,
            'not_found' => max(0, count($uuids) - $deleted),
        ];
    }

    /**
     * @return array{deleted: int, cancelled: int}
     */
    public function clearAllDeployments(int $adminId): array
    {
        $deleted = 0;
        $cancelled = 0;

        $deploymentIds = PluginDeployment::query()
            ->where('admin_id', $adminId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($deploymentIds as $deploymentId) {
            $deployment = PluginDeployment::query()->find($deploymentId);
            if (! $deployment) {
                continue;
            }

            if ($deployment->isActive()) {
                $cancelled++;
            }

            $this->deleteDeployment($deployment);
            $deleted++;
        }

        return [
            'deleted' => $deleted,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * @return array{pending: int, processing: int, processed: int, progress_percent: int}
     */
    public function progressMetrics(PluginDeployment $deployment): array
    {
        $counts = PluginDeploymentItem::query()
            ->where('plugin_deployment_id', $deployment->id)
            ->selectRaw("
                SUM(CASE WHEN item_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN item_status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN item_status IN ('success', 'failed', 'skipped') THEN 1 ELSE 0 END) as processed_count
            ")
            ->first();

        $pending = (int) ($counts->pending_count ?? 0);
        $processing = (int) ($counts->processing_count ?? 0);
        $processed = (int) ($counts->processed_count ?? 0);
        $total = max(1, $deployment->total_count);
        $progressPercent = (int) min(100, round(($processed / $total) * 100));

        if ($deployment->isFinished()) {
            $progressPercent = 100;
        }

        return [
            'pending' => $pending,
            'processing' => $processing,
            'processed' => $processed,
            'progress_percent' => $progressPercent,
        ];
    }

    public function retryFailed(PluginDeployment $original, int $adminId, string $scope = 'both'): PluginDeployment
    {
        return $this->retryFailedOrSkipped($original, $adminId, $scope);
    }

    /**
     * Create a new deployment for domains that failed and/or were skipped.
     *
     * @param  'failed'|'skipped'|'both'  $scope
     */
    public function retryFailedOrSkipped(
        PluginDeployment $original,
        int $adminId,
        string $scope = 'both'
    ): PluginDeployment {
        $statuses = match ($scope) {
            'failed' => ['failed'],
            'skipped' => ['skipped'],
            default => ['failed', 'skipped'],
        };

        $domains = $original->items()
            ->whereIn('item_status', $statuses)
            ->orderBy('sort_order')
            ->pluck('domain')
            ->unique()
            ->values()
            ->all();

        if ($domains === []) {
            throw new \RuntimeException('No failed or skipped domains to retry.');
        }

        $package = $original->pluginPackage;
        if (! $package) {
            throw new \RuntimeException('Original package not found.');
        }

        return $this->createDeployment(
            $adminId,
            $package,
            $original->operation,
            $domains,
            'manual',
            null,
            $original->activate_after,
            $original->skip_if_same_version
        );
    }

    /**
     * Apply history list filters to a deployments query.
     *
     * @param  Builder<PluginDeployment>  $query
     * @param  array{status?: string, operation?: string, outcome?: string, q?: string}  $filters
     * @return Builder<PluginDeployment>
     */
    public function applyHistoryFilters(Builder $query, array $filters): Builder
    {
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['queued', 'running', 'completed', 'cancelled', 'failed'], true)) {
            $query->where('status', $status);
        }

        $operation = (string) ($filters['operation'] ?? '');
        if (in_array($operation, PluginDeployment::OPERATIONS, true)) {
            $query->where('operation', $operation);
        }

        $outcome = (string) ($filters['outcome'] ?? '');
        match ($outcome) {
            'has_failed' => $query->where('failed_count', '>', 0),
            'has_skipped' => $query->where('skipped_count', '>', 0),
            'has_success' => $query->where('success_count', '>', 0),
            default => null,
        };

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('uuid', 'like', '%'.$q.'%')
                    ->orWhereHas('pluginPackage', function ($packageQuery) use ($q) {
                        $packageQuery->where('name', 'like', '%'.$q.'%')
                            ->orWhere('slug', 'like', '%'.$q.'%')
                            ->orWhere('expected_slug', 'like', '%'.$q.'%');
                    });
            });
        }

        return $query;
    }

    public function chunkSize(): int
    {
        return max(1, min(5, (int) config('plugin_manager.chunk_size', 2)));
    }

    public function jobTimeoutSeconds(): int
    {
        // Per item: inventory plus at most initial, corrective, and ambiguity-resolving actions.
        $worstCaseRemoteSeconds = $this->remotePluginManager->requestTimeout()
            * $this->remotePluginManager->retryAttempts()
            * 4
            * $this->chunkSize();

        return max(
            $worstCaseRemoteSeconds + 60,
            (int) config('plugin_manager.job_timeout', 400)
        );
    }

    public function lockSeconds(): int
    {
        return max($this->jobTimeoutSeconds() + 30, (int) config('plugin_manager.lock_seconds', 420));
    }

    /**
     * Reset items left in-flight after a worker timeout or crash so they can be picked up again.
     */
    public function recoverOrphanedItems(PluginDeployment $deployment): int
    {
        return PluginDeploymentItem::query()
            ->where('plugin_deployment_id', $deployment->id)
            ->where('item_status', 'processing')
            ->update(['item_status' => 'pending']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PluginDeploymentItem>
     */
    public function progressItems(PluginDeployment $deployment, ?string $since = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $deployment->items()
            ->orderBy('sort_order')
            ->select([
                'id', 'domain', 'sort_order', 'item_status', 'operation_result',
                'version_before', 'version_after', 'plugin_file', 'resolved_via',
                'error_code', 'message',
                'category', 'response_time_ms', 'http_status', 'request_url',
                'probe_method', 'audit_trail', 'retry_count', 'processed_at', 'updated_at',
            ]);

        if ($since !== null && $since !== '') {
            try {
                $sinceTime = \Illuminate\Support\Carbon::parse($since);
                $query->where(function ($builder) use ($sinceTime) {
                    $builder->where('updated_at', '>', $sinceTime)
                        ->orWhere('item_status', 'processing');
                });
            } catch (\Throwable) {
                // Invalid timestamp — return full list for safety.
            }
        }

        return $query->get();
    }
}
