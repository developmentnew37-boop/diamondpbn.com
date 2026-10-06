<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\PluginInventoryReport;
use App\Services\PluginDeploymentService;
use App\Services\PluginInventoryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PluginInventoryReportController extends Controller
{
    public function __construct(
        private readonly PluginDeploymentService $deploymentService,
        private readonly PluginInventoryReportService $reportService,
    ) {}

    public function create(): View
    {
        $categories = DomainCategory::query()
            ->withCount('domains')
            ->orderBy('name')
            ->get();

        $reports = PluginInventoryReport::query()
            ->visibleTo($this->actingAdmin())
            ->with('domainCategory:id,name')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('admin.domains.plugin-manager.report', [
            'domainCategories' => $categories,
            'reports' => $reports,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|in:manual,inventory',
            'domains_list' => 'required_if:source,manual|nullable|string|max:100000',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
        ]);

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
                'message' => 'Maximum '.$this->deploymentService->maxDomains().' domains per report.',
            ], 422);
        }

        try {
            $report = $this->reportService->createReport(
                (int) Auth::guard('admin')->id(),
                $domains,
                $source,
                $source === 'inventory' ? $categoryId : null
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not start the report: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'report_uuid' => $report->uuid,
            'total' => $report->total_count,
            'truncated' => $truncated,
            'total_in_scope' => $totalInScope,
            'redirect_url' => route('admin.plugin-manager.reports.show', $report->uuid),
            'message' => 'Plugin report started in the background.',
        ]);
    }

    public function show(string $uuid): View
    {
        $report = $this->visibleReports()->with('domainCategory:id,name')->where('uuid', $uuid)->firstOrFail();

        return view('admin.domains.plugin-manager.report-show', [
            'report' => $report,
        ]);
    }

    public function progress(string $uuid): JsonResponse
    {
        $report = $this->visibleReports()->where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'success' => true,
            'status' => $report->status,
            'processed_count' => (int) $report->processed_count,
            'total_count' => (int) $report->total_count,
            'success_count' => (int) $report->success_count,
            'failed_count' => (int) $report->failed_count,
            'percent' => $report->progressPercent(),
            'status_message' => $report->status_message,
            'download_ready' => $report->status === 'completed' && $this->reportService->workbookAbsolutePath($report) !== null,
            'finished' => $report->isFinished(),
        ]);
    }

    public function download(string $uuid): BinaryFileResponse
    {
        $report = $this->visibleReports()->where('uuid', $uuid)->firstOrFail();
        $path = $this->reportService->workbookAbsolutePath($report);

        abort_unless($report->status === 'completed' && $path !== null, 404);

        return response()->download($path, 'plugin-report-'.$report->uuid.'.xlsx');
    }

    public function cancel(string $uuid): JsonResponse
    {
        $report = $this->visibleReports()->where('uuid', $uuid)->firstOrFail();
        $this->reportService->cancel($report);

        return response()->json(['success' => true, 'message' => 'Report cancelled.']);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $report = $this->visibleReports()->where('uuid', $uuid)->firstOrFail();
        $this->reportService->deleteReport($report);

        return response()->json(['success' => true, 'message' => 'Report deleted.']);
    }

    private function actingAdmin(): Admin
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }

    private function visibleReports()
    {
        return PluginInventoryReport::query()->visibleTo($this->actingAdmin());
    }
}
