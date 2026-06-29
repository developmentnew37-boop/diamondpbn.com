<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginPackage;
use App\Services\PluginDeploymentService;
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

    public function index(): View
    {
        $deployments = PluginDeployment::query()
            ->with(['pluginPackage:id,name,slug,version', 'domainCategory:id,name'])
            ->where('admin_id', Auth::guard('admin')->id())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.domains.plugin-manager.deployments-index', [
            'deployments' => $deployments,
        ]);
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
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'plugin_package_uuid' => 'required|string|exists:plugin_packages,uuid',
            'operation' => 'required|in:'.implode(',', PluginDeployment::OPERATIONS),
            'source' => 'required|in:manual,inventory',
            'domains_list' => 'required_if:source,manual|nullable|string|max:100000',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
            'activate_after' => 'nullable|boolean',
            'skip_if_same_version' => 'nullable|boolean',
        ]);

        $package = PluginPackage::query()->where('uuid', $request->input('plugin_package_uuid'))->firstOrFail();
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
            $deployment = $this->deploymentService->createDeployment(
                $adminId,
                $package,
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

        return response()->json([
            'success' => true,
            'deployment_uuid' => $deployment->uuid,
            'total' => $deployment->total_count,
            'truncated' => $truncated,
            'total_in_scope' => $totalInScope,
            'redirect_url' => route('admin.plugin-manager.deployments.show', $deployment->uuid),
            'message' => 'Deployment started in background.',
        ]);
    }

    public function show(string $uuid): View
    {
        $deployment = PluginDeployment::query()
            ->with(['pluginPackage', 'domainCategory'])
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->firstOrFail();

        return view('admin.domains.plugin-manager.deployment-show', [
            'deployment' => $deployment,
        ]);
    }

    public function progress(Request $request, string $uuid): JsonResponse
    {
        $deployment = PluginDeployment::query()
            ->with('pluginPackage')
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
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
                'error_code' => $item->error_code,
                'message' => $item->message,
                'category' => $item->category,
                'response_time_ms' => $item->response_time_ms,
                'processed_at' => $item->processed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function retryFailed(string $uuid): JsonResponse
    {
        $deployment = PluginDeployment::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->firstOrFail();

        try {
            $retry = $this->deploymentService->retryFailed($deployment, (int) Auth::guard('admin')->id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'deployment_uuid' => $retry->uuid,
            'redirect_url' => route('admin.plugin-manager.deployments.show', $retry->uuid),
            'message' => 'Retry deployment started for failed domains.',
        ]);
    }

    public function cancel(string $uuid): JsonResponse
    {
        $deployment = PluginDeployment::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->firstOrFail();

        $this->deploymentService->cancelDeployment($deployment);

        return response()->json(['success' => true, 'message' => 'Deployment cancelled.']);
    }

    public function exportFailures(string $uuid): StreamedResponse
    {
        $deployment = PluginDeployment::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->firstOrFail();

        $filename = 'plugin-deployment-failures-'.$deployment->uuid.'.csv';

        return response()->streamDownload(function () use ($deployment) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['domain', 'category', 'error_code', 'message', 'version_before']);

            $deployment->items()
                ->where('item_status', 'failed')
                ->orderBy('sort_order')
                ->each(function ($item) use ($handle) {
                    fputcsv($handle, [
                        $item->domain,
                        $item->category,
                        $item->error_code,
                        $item->message,
                        $item->version_before,
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
