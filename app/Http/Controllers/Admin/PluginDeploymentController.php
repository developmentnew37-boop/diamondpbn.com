<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginPackage;
use App\Services\PluginDeploymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PluginDeploymentController extends Controller
{
    public function __construct(
        private readonly PluginDeploymentService $deploymentService
    ) {}

    public function index(Request $request): View
    {
        $admin = $this->actingAdmin();
        $canSeeAll = $admin->isSuperAdmin();
        $filters = $this->historyFiltersFromRequest($request, $canSeeAll);
        $baseQuery = $this->historyBaseQuery($filters);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->whereIn('status', ['queued', 'running'])->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'domains_processed' => (int) (clone $baseQuery)->sum('processed_count'),
        ];

        $listQuery = $this->deploymentService->applyHistoryFilters(clone $baseQuery, $filters);

        $deployments = $listQuery
            ->with([
                'pluginPackage:id,name,slug,expected_slug,version',
                'domainCategory:id,name',
                'admin:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.domains.plugin-manager.deployments-index', [
            'deployments' => $deployments,
            'stats' => $stats,
            'filters' => $filters,
            'operations' => PluginDeployment::OPERATIONS,
            'hasFilters' => $this->historyHasActiveFilters($filters),
            'canSeeAllHistory' => $canSeeAll,
            'historyAdmins' => $canSeeAll
                ? Admin::query()->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function exportHistory(Request $request): StreamedResponse
    {
        $admin = $this->actingAdmin();
        $canSeeAll = $admin->isSuperAdmin();
        $filters = $this->historyFiltersFromRequest($request, $canSeeAll);

        $query = $this->deploymentService->applyHistoryFilters(
            $this->historyBaseQuery($filters),
            $filters
        )->with([
            'pluginPackage:id,name,slug,expected_slug,version',
            'admin:id,name,email',
        ])->orderByDesc('created_at');

        $filename = 'plugin-deployment-history-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $canSeeAll) {
            $handle = fopen('php://output', 'w');
            $headers = [
                'uuid',
                'package',
                'operation',
                'status',
                'total',
                'success',
                'failed',
                'skipped',
                'created_at',
                'completed_at',
            ];
            if ($canSeeAll) {
                $headers[] = 'started_by';
            }
            fputcsv($handle, $headers);

            $query->chunkById(200, function ($deployments) use ($handle, $canSeeAll) {
                foreach ($deployments as $deployment) {
                    $row = [
                        $deployment->uuid,
                        $deployment->pluginPackage?->displayLabel() ?? '',
                        $deployment->operation,
                        $deployment->status,
                        $deployment->total_count,
                        $deployment->success_count,
                        $deployment->failed_count,
                        $deployment->skipped_count,
                        optional($deployment->created_at)?->toDateTimeString(),
                        optional($deployment->completed_at)?->toDateTimeString(),
                    ];
                    if ($canSeeAll) {
                        $row[] = $deployment->admin?->name ?? '';
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request): View
    {
        $categories = DomainCategory::query()
            ->withCount('domains')
            ->orderBy('name')
            ->get();

        $packages = PluginPackage::query()->orderByDesc('created_at')->get();
        $selectedPackageUuid = $request->query('package');

        return view('admin.domains.plugin-manager.deploy', [
            'domainCategories' => $categories,
            'packages' => $packages,
            'selectedPackageUuid' => $selectedPackageUuid,
            'operations' => PluginDeployment::OPERATIONS,
            'maxPlugins' => $this->deploymentService->maxPluginsPerBatch(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'plugin_package_uuid' => 'nullable|string|exists:plugin_packages,uuid',
            'plugin_package_uuids' => 'nullable|array|max:'.$this->deploymentService->maxPluginsPerBatch(),
            'plugin_package_uuids.*' => 'string|distinct|exists:plugin_packages,uuid',
            'operation' => 'required|in:'.implode(',', PluginDeployment::OPERATIONS),
            'source' => 'required|in:manual,inventory',
            'domains_list' => 'required_if:source,manual|nullable|string|max:100000',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
            'activate_after' => 'nullable|boolean',
            'skip_if_same_version' => 'nullable|boolean',
        ]);

        $packageUuids = $this->selectedPackageUuids($request);
        if ($packageUuids === []) {
            return response()->json(['success' => false, 'message' => 'Select at least one plugin.'], 422);
        }

        if (count($packageUuids) > $this->deploymentService->maxPluginsPerBatch()) {
            return response()->json([
                'success' => false,
                'message' => 'Select at most '.$this->deploymentService->maxPluginsPerBatch().' plugins.',
            ], 422);
        }

        $packagesByUuid = PluginPackage::query()->whereIn('uuid', $packageUuids)->get()->keyBy('uuid');
        $packages = [];
        foreach ($packageUuids as $uuid) {
            $package = $packagesByUuid->get($uuid);
            if (! $package) {
                return response()->json(['success' => false, 'message' => 'One of the selected plugins was not found.'], 422);
            }
            $packages[] = $package;
        }

        $source = $request->input('source', 'manual');
        $truncated = false;
        $totalInScope = null;
        $categoryId = $request->filled('domain_category_id') ? (int) $request->input('domain_category_id') : null;

        if ($source === 'inventory') {
            if ($categoryId === null) {
                return response()->json(['success' => false, 'message' => 'Select a domain category.'], 422);
            }

            $inventory = $this->deploymentService->getDomainNamesFromInventory($categoryId);
            $domains = $inventory['names'];
            $totalInScope = $inventory['total_in_scope'];
            $truncated = $totalInScope > count($domains);

            if ($domains === []) {
                return response()->json(['success' => false, 'message' => 'No domains in selected category.'], 422);
            }
        } else {
            $domains = $this->deploymentService->parseDomainList($request->input('domains_list', ''));

            if ($domains === []) {
                return response()->json(['success' => false, 'message' => 'No valid domains found.'], 422);
            }
        }

        if (count($domains) > $this->deploymentService->maxDomains()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum '.$this->deploymentService->maxDomains().' domains per deployment.',
            ], 422);
        }

        $adminId = (int) Auth::guard('admin')->id();

        try {
            $deployments = $this->deploymentService->createSequentialDeployments(
                $adminId,
                $packages,
                $request->input('operation'),
                $domains,
                $source,
                $source === 'inventory' ? $categoryId : null,
                $request->boolean('activate_after', true),
                $request->boolean('skip_if_same_version', true)
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not start deployment: '.$e->getMessage()], 500);
        }

        $deployment = $deployments[0];

        return response()->json([
            'success' => true,
            'deployment_uuid' => $deployment->uuid,
            'deployment_count' => count($deployments),
            'total' => $deployment->total_count,
            'truncated' => $truncated,
            'total_in_scope' => $totalInScope,
            'redirect_url' => route('admin.plugin-manager.deployments.show', $deployment->uuid),
            'message' => 'Deployment started in background.',
        ]);
    }

    public function show(string $uuid): View
    {
        $deployment = $this->visibleDeployments()
            ->with(['pluginPackage', 'domainCategory', 'admin:id,name,email'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $nextInBatch = null;
        if ($deployment->batch_uuid) {
            $nextInBatch = PluginDeployment::query()
                ->with('pluginPackage:id,name,version')
                ->where('batch_uuid', $deployment->batch_uuid)
                ->where('batch_position', (int) $deployment->batch_position + 1)
                ->first();
        }

        return view('admin.domains.plugin-manager.deployment-show', [
            'deployment' => $deployment,
            'canSeeAllHistory' => $this->actingAdmin()->isSuperAdmin(),
            'nextInBatch' => $nextInBatch,
        ]);
    }

    public function progress(Request $request, string $uuid): JsonResponse
    {
        $deployment = $this->visibleDeployments()
            ->with('pluginPackage')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $metrics = $this->deploymentService->progressMetrics($deployment);

        $items = $this->deploymentService->progressItems($deployment, $request->query('since'));

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'deployment' => [
                'uuid' => $deployment->uuid,
                'status' => $deployment->status,
                'phase' => $deployment->phase,
                'operation' => $deployment->operation,
                'status_message' => $deployment->status_message,
                'package' => $deployment->pluginPackage?->displayLabel(),
                'total' => $deployment->total_count,
                'processed' => $deployment->processed_count,
                'success' => $deployment->success_count,
                'failed' => $deployment->failed_count,
                'skipped' => $deployment->skipped_count,
                'pending' => $metrics['pending'] + $metrics['processing'],
                'progress_percent' => $metrics['progress_percent'],
                'started_at' => $deployment->started_at?->toIso8601String(),
                'completed_at' => $deployment->completed_at?->toIso8601String(),
                'is_finished' => $deployment->isFinished(),
            ],
            'results' => $items->map(fn ($item) => [
                'id' => $item->id,
                'index' => $item->sort_order,
                'domain' => $item->domain,
                'item_status' => $item->item_status,
                'operation_result' => $item->operation_result,
                'version_before' => $item->version_before,
                'version_after' => $item->version_after,
                'plugin_file' => $item->plugin_file,
                'resolved_via' => $item->resolved_via,
                'error_code' => $item->error_code,
                'message' => $item->message,
                'category' => $item->category,
                'response_time_ms' => $item->response_time_ms,
                'http_status' => $item->http_status,
                'probe_method' => $item->probe_method,
                'retry_count' => $item->retry_count,
                'audit_attempts' => is_array($item->audit_trail) ? count($item->audit_trail) : 0,
                'processed_at' => $item->processed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function retryFailed(Request $request, string $uuid): JsonResponse
    {
        $deployment = $this->visibleDeployments()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $scope = (string) $request->input('scope', $request->query('scope', 'both'));
        if (! in_array($scope, ['failed', 'skipped', 'both'], true)) {
            $scope = 'both';
        }

        try {
            $retry = $this->deploymentService->retryFailedOrSkipped(
                $deployment,
                (int) Auth::guard('admin')->id(),
                $scope
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'deployment_uuid' => $retry->uuid,
            'redirect_url' => route('admin.plugin-manager.deployments.show', $retry->uuid),
            'message' => 'Retry deployment started for failed/skipped domains.',
        ]);
    }

    public function cancel(string $uuid): JsonResponse
    {
        $deployment = $this->visibleDeployments()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->deploymentService->cancelDeployment($deployment);

        return response()->json(['success' => true, 'message' => 'Deployment cancelled.']);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $deployment = $this->visibleDeployments()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $wasActive = $deployment->isActive();

        try {
            $this->deploymentService->deleteDeployment($deployment);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not delete deployment.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $wasActive
                ? 'Active deployment cancelled and removed from history.'
                : 'Deployment removed from history.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuids' => 'required|array|min:1|max:100',
            'uuids.*' => 'required|string|uuid',
        ]);

        $admin = $this->actingAdmin();

        try {
            $result = $this->deploymentService->bulkDeleteDeployments(
                $validated['uuids'],
                (int) $admin->id,
                ! $admin->isSuperAdmin()
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not delete selected deployments.'], 500);
        }

        if ($result['deleted'] === 0) {
            return response()->json(['success' => false, 'message' => 'No matching deployments found.'], 422);
        }

        $message = $result['deleted'].' deployment(s) removed from history.';
        if ($result['cancelled'] > 0) {
            $message .= ' '.$result['cancelled'].' active run(s) were cancelled first.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted' => $result['deleted'],
        ]);
    }

    public function clearHistory(): JsonResponse
    {
        $adminId = (int) Auth::guard('admin')->id();

        try {
            $result = $this->deploymentService->clearAllDeployments($adminId);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not clear deployment history.'], 500);
        }

        if ($result['deleted'] === 0) {
            return response()->json(['success' => true, 'message' => 'History is already empty.', 'deleted' => 0]);
        }

        $message = 'Cleared '.$result['deleted'].' deployment record(s) from history.';
        if ($result['cancelled'] > 0) {
            $message .= ' '.$result['cancelled'].' active run(s) were cancelled first.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted' => $result['deleted'],
        ]);
    }

    public function exportFailures(Request $request, string $uuid): StreamedResponse
    {
        $deployment = $this->visibleDeployments()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $status = strtolower((string) $request->query('status', 'all'));
        if (! in_array($status, ['failed', 'skipped', 'success', 'all'], true)) {
            $status = 'all';
        }

        $filename = 'plugin-deployment-'.$status.'-'.$deployment->uuid.'.csv';

        return response()->streamDownload(function () use ($deployment, $status) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'domain',
                'category',
                'item_status',
                'error_code',
                'message',
                'version_before',
                'version_after',
                'http_status',
                'probe_method',
                'retry_count',
                'request_url',
            ]);

            $query = $deployment->items()->orderBy('sort_order');
            if ($status !== 'all') {
                $query->where('item_status', $status);
            }

            $query->each(function ($item) use ($handle) {
                fputcsv($handle, [
                    $item->domain,
                    $item->category,
                    $item->item_status,
                    $item->error_code,
                    $item->message,
                    $item->version_before,
                    $item->version_after,
                    $item->http_status,
                    $item->probe_method,
                    $item->retry_count,
                    $item->request_url,
                ]);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<string>
     */
    private function selectedPackageUuids(Request $request): array
    {
        $uuids = [];

        foreach ((array) $request->input('plugin_package_uuids', []) as $uuid) {
            if (! is_string($uuid) || $uuid === '' || in_array($uuid, $uuids, true)) {
                continue;
            }
            $uuids[] = $uuid;
        }

        if ($uuids === [] && is_string($request->input('plugin_package_uuid')) && $request->input('plugin_package_uuid') !== '') {
            $uuids[] = $request->input('plugin_package_uuid');
        }

        return $uuids;
    }

    private function actingAdmin(): Admin
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }

    private function visibleDeployments(): Builder
    {
        return PluginDeployment::query()->visibleTo($this->actingAdmin());
    }

    /**
     * @param  array{status: string, operation: string, outcome: string, q: string, admin_id: string}  $filters
     */
    private function historyBaseQuery(array $filters): Builder
    {
        $query = $this->visibleDeployments();

        if ($filters['admin_id'] !== '' && ctype_digit($filters['admin_id'])) {
            $query->where('admin_id', (int) $filters['admin_id']);
        }

        return $query;
    }

    /**
     * @return array{status: string, operation: string, outcome: string, q: string, admin_id: string}
     */
    private function historyFiltersFromRequest(Request $request, bool $canSeeAll): array
    {
        $adminId = trim((string) $request->query('admin_id', ''));

        return [
            'status' => (string) $request->query('status', ''),
            'operation' => (string) $request->query('operation', ''),
            'outcome' => (string) $request->query('outcome', ''),
            'q' => trim((string) $request->query('q', '')),
            'admin_id' => $canSeeAll && ctype_digit($adminId) ? $adminId : '',
        ];
    }

    /**
     * @param  array{status: string, operation: string, outcome: string, q: string, admin_id: string}  $filters
     */
    private function historyHasActiveFilters(array $filters): bool
    {
        return $filters['status'] !== ''
            || $filters['operation'] !== ''
            || $filters['outcome'] !== ''
            || $filters['q'] !== ''
            || $filters['admin_id'] !== '';
    }
}
