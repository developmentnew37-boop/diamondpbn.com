<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainStatusCheck;
use App\Services\DomainStatusCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DomainStatusCheckerController extends Controller
{
    public function __construct(
        private readonly DomainStatusCheckerService $statusChecker
    ) {}

    public function index(): View
    {
        return view('admin.domains.status-checker', [
            'domainCategories' => DomainCategory::orderBy('name')->get(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|in:manual,inventory',
            'domains_list' => 'required_if:source,manual|nullable|string|max:100000',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
            'update_inventory' => 'nullable|boolean',
        ]);

        $source = $request->input('source', 'manual');
        $updateInventory = $request->boolean('update_inventory');
        $truncated = false;
        $totalInScope = null;
        $selectedCategoryId = $request->filled('domain_category_id')
            ? (int) $request->input('domain_category_id')
            : null;

        if ($source === 'inventory') {
            $inventory = $this->statusChecker->getDomainNamesFromInventory($selectedCategoryId);
            $domains = $inventory['names'];
            $totalInScope = $inventory['total_in_scope'];
            $truncated = $totalInScope > count($domains);
            $updateInventory = true;

            if ($domains === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No domains found in the selected inventory scope.',
                ], 422);
            }
        } else {
            $domains = $this->statusChecker->parseDomainList($request->input('domains_list', ''));

            if ($domains === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid domains found. Enter one domain per line (example.com).',
                ], 422);
            }
        }

        if (count($domains) > $this->statusChecker->maxDomains()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum '.$this->statusChecker->maxDomains().' domains allowed per check.',
            ], 422);
        }

        $adminId = (int) Auth::guard('admin')->id();

        try {
            $check = $this->statusChecker->createCheck(
                $adminId,
                $domains,
                $source,
                $updateInventory,
                $source === 'inventory' ? $selectedCategoryId : null
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not start status check: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'check_uuid' => $check->uuid,
            'total' => $check->total_count,
            'truncated' => $truncated,
            'total_in_scope' => $totalInScope,
            'message' => 'Status check started in background.',
        ]);
    }

    public function progress(Request $request, string $uuid): JsonResponse
    {
        $check = DomainStatusCheck::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->firstOrFail();

        $metrics = $this->statusChecker->progressMetrics($check);

        $items = $this->statusChecker->progressItems($check, $request->query('since'));

        $pendingCount = $metrics['pending'] + $metrics['checking'] + $metrics['retry_pending'];

        return response()->json([
            'success' => true,
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
                'in_inventory' => $check->in_inventory_count,
                'not_in_inventory' => max(0, $check->total_count - $check->in_inventory_count),
                'inventory_updated' => $check->inventory_updated_count,
                'update_inventory' => $check->update_inventory,
                'started_at' => $check->started_at?->toIso8601String(),
                'completed_at' => $check->completed_at?->toIso8601String(),
                'is_finished' => $check->isFinished(),
            ],
            'results' => $items->map(fn ($item) => [
                'id' => $item->id,
                'index' => $item->sort_order,
                'domain' => $item->domain,
                'check_status' => $item->check_status,
                'connected' => $item->connected,
                'message' => $item->message,
                'attempts' => $item->attempts,
                'response_time_ms' => $item->response_time_ms,
                'in_inventory' => $item->in_inventory,
                'category' => $item->category,
                'checked_at' => $item->checked_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
