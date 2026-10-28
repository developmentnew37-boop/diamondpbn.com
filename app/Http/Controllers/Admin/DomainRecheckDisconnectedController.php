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

class DomainRecheckDisconnectedController extends Controller
{
    public function __construct(
        private readonly DomainStatusCheckerService $statusChecker
    ) {}

    public function index(Request $request): View
    {
        $selectedCategoryId = $request->filled('domain_category_id')
            ? (int) $request->input('domain_category_id')
            : null;

        $snapshot = $this->statusChecker->disconnectedScopeSnapshot($selectedCategoryId);
        $adminId = (int) Auth::guard('admin')->id();
        $disconnectedRechecks = $this->statusChecker->visibleDisconnectedChecks($adminId);
        $checkUuid = $request->query('check');
        $resumeCheck = $this->statusChecker->openDisconnectedCheck(
            $adminId,
            is_string($checkUuid) ? $checkUuid : null,
            $selectedCategoryId
        );
        if ($resumeCheck?->isFinished() && $resumeCheck->dismissed_at !== null) {
            $resumeCheck = null;
        }
        $resumeBootstrap = $resumeCheck
            ? $this->statusChecker->disconnectedProgressPayload($resumeCheck, null, false)
            : null;

        return view('admin.domains.recheck-disconnected', [
            'domainCategories' => DomainCategory::orderBy('name')->get(),
            'selectedCategoryId' => $selectedCategoryId,
            'disconnectedTotal' => $snapshot['total_in_scope'],
            'disconnectedByCategory' => $snapshot['by_category'],
            'disconnectedMax' => $this->statusChecker->disconnectedMaxDomains(),
            'disconnectedRechecks' => $disconnectedRechecks,
            'recheckBusy' => $disconnectedRechecks->contains(fn (DomainStatusCheck $check) => ! $check->isFinished()),
            'resumeCategoryLabel' => $this->statusChecker->disconnectedCategoryLabel($resumeCheck),
            'resumeBootstrap' => $resumeBootstrap,
        ]);
    }

    public function campaigns(): View
    {
        return view('admin.domains.recheck-campaigns', [
            'rechecks' => $this->statusChecker->visibleDisconnectedChecks((int) Auth::guard('admin')->id()),
        ]);
    }

    public function preview(string $uuid): View
    {
        $check = $this->ownedDisconnectedCheck($uuid);

        $items = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(50);

        return view('admin.domains.recheck-campaign-preview', [
            'check' => $check,
            'items' => $items,
            'categoryLabel' => $this->statusChecker->disconnectedCategoryLabel($check),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
        ]);

        $selectedCategoryId = $request->filled('domain_category_id')
            ? (int) $request->input('domain_category_id')
            : null;

        $inventory = $this->statusChecker->getDisconnectedDomainNames($selectedCategoryId);
        $domains = $inventory['names'];
        $totalInScope = $inventory['total_in_scope'];
        $truncated = $totalInScope > count($domains);

        if ($domains === []) {
            return response()->json([
                'success' => false,
                'message' => 'No disconnected domains found in the selected scope.',
            ], 422);
        }

        $max = $this->statusChecker->disconnectedMaxDomains();
        if (count($domains) > $max) {
            return response()->json([
                'success' => false,
                'message' => "Maximum {$max} disconnected domains allowed per recheck.",
            ], 422);
        }

        $adminId = (int) Auth::guard('admin')->id();

        try {
            $check = $this->statusChecker->createCheck(
                $adminId,
                $domains,
                'disconnected',
                true,
                $selectedCategoryId,
                true
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not start disconnected recheck: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'check_uuid' => $check->uuid,
            'total' => $check->total_count,
            'truncated' => $truncated,
            'total_in_scope' => $totalInScope,
            'message' => 'Disconnected recheck started in background.',
        ]);
    }

    public function progress(Request $request, string $uuid): JsonResponse
    {
        $check = DomainStatusCheck::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->where('source', 'disconnected')
            ->firstOrFail();

        $payload = $this->statusChecker->disconnectedProgressPayload(
            $check,
            $request->query('since')
        );

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function cancel(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $check = $this->ownedDisconnectedCheck($uuid);

        if ($check->isFinished()) {
            $message = 'This recheck is already finished.';

            if (! $request->expectsJson()) {
                return redirect()
                    ->route('admin.domain.recheck-disconnected.campaigns.show', $check->uuid)
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
        $message = 'Recheck cancelled. Leftover queue jobs were purged.';

        if (! $request->expectsJson()) {
            return redirect()
                ->route('admin.domain.recheck-disconnected.campaigns.show', $check->uuid)
                ->with('cus__success', $message);
        }

        return response()->json([
            'success' => true,
            'status' => $check->status,
            'message' => $message,
        ]);
    }

    public function clear(string $uuid): JsonResponse
    {
        $check = $this->ownedDisconnectedCheck($uuid);

        if (! $check->isFinished()) {
            return response()->json([
                'success' => false,
                'message' => 'This recheck is still running.',
            ], 409);
        }

        $check->forceFill(['dismissed_at' => now()])->save();

        return response()->json([
            'cleared' => true,
        ]);
    }

    public function export(string $uuid): StreamedResponse
    {
        $check = $this->ownedDisconnectedCheck($uuid);

        if (! $check->isFinished()) {
            abort(409, 'Recheck is still running. Export is available after completion.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'recheck');
        $xlsxPath = $temporary.'.xlsx';
        rename($temporary, $xlsxPath);

        $writer = SimpleExcelWriter::create($xlsxPath);
        $writer->addHeader(['Domain', 'Category', 'Status', 'Code', 'Message']);

        foreach ($this->statusChecker->csvRowsForCheck($check) as $row) {
            $writer->addRow([
                (string) ($row[0] ?? ''),
                (string) ($row[1] ?? ''),
                (string) ($row[3] ?? ''),
                (string) ($row[4] ?? ''),
                (string) ($row[5] ?? ''),
            ]);
        }

        $fileName = 'disconnected-recheck-'.$check->uuid.'.xlsx';

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

    private function ownedDisconnectedCheck(string $uuid): DomainStatusCheck
    {
        return DomainStatusCheck::query()
            ->with('domainCategory:id,name')
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->where('source', 'disconnected')
            ->firstOrFail();
    }
}
