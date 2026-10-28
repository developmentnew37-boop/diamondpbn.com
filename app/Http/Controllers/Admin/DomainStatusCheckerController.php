<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use App\Services\DomainStatusCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DomainStatusCheckerController extends Controller
{
    public function __construct(
        private readonly DomainStatusCheckerService $statusChecker
    ) {}

    public function index(Request $request): View
    {
        $adminId = (int) Auth::guard('admin')->id();
        $checkUuid = $request->query('check');
        $resumeCheck = $this->statusChecker->openUnfinishedStatusCheckerCheck(
            $adminId,
            is_string($checkUuid) ? $checkUuid : null
        );

        return view('admin.domains.status-checker', [
            'domainCategories' => DomainCategory::orderBy('name')->get(),
            'checkerBusy' => $this->statusChecker->statusCheckerIsBusy($adminId),
            'resumeBootstrap' => $resumeCheck
                ? $this->progressPayload($resumeCheck, null)
                : null,
        ]);
    }

    public function campaigns(): View
    {
        return view('admin.domains.status-checker-campaigns', [
            'checks' => $this->statusChecker->visibleStatusCheckerChecks((int) Auth::guard('admin')->id()),
        ]);
    }

    public function preview(Request $request, string $uuid): View
    {
        $check = $this->ownedStatusCheckerCheck($uuid);
        $result = $request->query('result');
        $resultFilter = in_array($result, ['connected', 'disconnected', 'verifying'], true) ? $result : 'all';
        $status = match ($resultFilter) {
            'connected' => 'connected',
            'disconnected' => 'disconnected',
            'verifying' => 'retry_pending',
            default => null,
        };

        $items = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->when($status !== null, fn ($query) => $query->where('check_status', $status))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $counts = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->selectRaw('check_status, COUNT(*) as total')
            ->groupBy('check_status')
            ->pluck('total', 'check_status');

        return view('admin.domains.status-checker-preview', [
            'check' => $check,
            'items' => $items,
            'runLabel' => $this->statusChecker->statusCheckerRunLabel($check),
            'resultFilter' => $resultFilter,
            'resultCounts' => [
                'all' => (int) $counts->sum(),
                'connected' => (int) ($counts['connected'] ?? 0),
                'disconnected' => (int) ($counts['disconnected'] ?? 0),
                'verifying' => (int) ($counts['retry_pending'] ?? 0),
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|in:manual,inventory',
            'domains_list' => 'required_if:source,manual|nullable|string|max:100000',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
            'update_inventory' => 'nullable|boolean',
            'use_authenticated_check' => 'nullable|boolean',
        ]);

        $adminId = (int) Auth::guard('admin')->id();

        if ($this->statusChecker->statusCheckerIsBusy($adminId)) {
            return response()->json([
                'success' => false,
                'message' => 'A status check is already running. Open Status campaigns or cancel it before starting another.',
            ], 409);
        }

        $source = $request->input('source', 'manual');
        $updateInventory = $request->boolean('update_inventory');
        $useAuthenticatedCheck = $source === 'inventory'
            ? $request->boolean('use_authenticated_check', true)
            : $request->boolean('use_authenticated_check');
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

        try {
            $check = $this->statusChecker->createCheck(
                $adminId,
                $domains,
                $source,
                $updateInventory,
                $source === 'inventory' ? $selectedCategoryId : null,
                $useAuthenticatedCheck
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
        $check = $this->ownedStatusCheckerCheck($uuid);
        $since = $request->query('since');

        return response()->json($this->progressPayload($check, is_string($since) ? $since : null));
    }

    public function cancel(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $check = $this->ownedStatusCheckerCheck($uuid);

        if ($check->isFinished()) {
            $message = 'This status check is already finished.';

            if (! $request->expectsJson()) {
                return redirect()
                    ->route('admin.domain.status-checker.campaigns.show', $check->uuid)
                    ->with('cus__success', $message);
            }

            return response()->json([
                'success' => true,
                'already_finished' => true,
                'status' => $check->status,
                'message' => $message,
            ]);
        }

        $check = $this->statusChecker->cancelCheck($check, 'Cancelled by admin');
        $message = 'Status check cancelled. Leftover queue jobs were purged.';

        if (! $request->expectsJson()) {
            return redirect()
                ->route('admin.domain.status-checker.campaigns.show', $check->uuid)
                ->with('cus__success', $message);
        }

        return response()->json([
            'success' => true,
            'status' => $check->status,
            'message' => $message,
        ]);
    }

    public function export(string $uuid): StreamedResponse
    {
        $check = $this->ownedStatusCheckerCheck($uuid);

        if (! $check->isFinished()) {
            abort(409, 'Status check is still running. Export is available after completion.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'status');
        $xlsxPath = $temporary.'.xlsx';
        rename($temporary, $xlsxPath);

        $writer = SimpleExcelWriter::create($xlsxPath);
        $writer->addHeader(['Domain', 'Category', 'Status', 'HTTP', 'Code', 'Message']);

        foreach ($this->statusChecker->statusCheckerExportRows($check) as $row) {
            $writer->addRow($row);
        }

        $fileName = 'status-check-'.$check->uuid.'.xlsx';

        return response()->streamDownload(function () use ($xlsxPath) {
            $handle = fopen($xlsxPath, 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
            @unlink($xlsxPath);
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function progressPayload(DomainStatusCheck $check, ?string $since): array
    {
        $metrics = $this->statusChecker->progressMetrics($check);
        $items = $this->statusChecker->progressItems($check, $since);
        $pendingCount = $metrics['pending'] + $metrics['checking'] + $metrics['retry_pending'];

        return [
            'success' => true,
            'cursor' => $this->statusChecker->nextProgressCursor($since, $items),
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
                'status_code' => $item->status_code,
                'probe_method' => $item->probe_method,
                'agent_version' => $item->agent_version,
                'http_status' => $item->http_status,
                'message' => $item->message,
                'attempts' => $item->attempts,
                'response_time_ms' => $item->response_time_ms,
                'in_inventory' => $item->in_inventory,
                'category' => $item->category,
                'checked_at' => $item->checked_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
            ])->values(),
        ];
    }

    private function ownedStatusCheckerCheck(string $uuid): DomainStatusCheck
    {
        return DomainStatusCheck::query()
            ->with('domainCategory:id,name')
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->whereIn('source', ['manual', 'inventory'])
            ->firstOrFail();
    }
}
