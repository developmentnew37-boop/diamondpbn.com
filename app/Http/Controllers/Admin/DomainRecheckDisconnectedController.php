<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainStatusCheck;
use App\Services\DomainStatusCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

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

        $snapshot = $this->statusChecker->getDisconnectedDomainNames($selectedCategoryId);

        $resumeCheck = $this->statusChecker->latestDisconnectedCheckForAdmin(
            (int) Auth::guard('admin')->id()
        );
        $resumeBootstrap = $resumeCheck
            ? $this->statusChecker->disconnectedProgressPayload($resumeCheck)
            : null;

        return view('admin.domains.recheck-disconnected', [
            'domainCategories' => DomainCategory::orderBy('name')->get(),
            'selectedCategoryId' => $selectedCategoryId,
            'disconnectedTotal' => $snapshot['total_in_scope'],
            'disconnectedByCategory' => $snapshot['by_category'],
            'disconnectedMax' => $this->statusChecker->disconnectedMaxDomains(),
            'resumeBootstrap' => $resumeBootstrap,
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

    public function cancel(string $uuid): JsonResponse
    {
        $check = DomainStatusCheck::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->where('source', 'disconnected')
            ->firstOrFail();

        if ($check->isFinished()) {
            return response()->json([
                'success' => true,
                'already_finished' => true,
                'status' => $check->status,
                'message' => 'This recheck is already finished.',
            ]);
        }

        $check = $this->statusChecker->cancelCheck($check, 'Cancelled by admin');

        return response()->json([
            'success' => true,
            'status' => $check->status,
            'message' => 'Recheck cancelled. Leftover queue jobs were purged.',
        ]);
    }

    public function export(string $uuid): StreamedResponse
    {
        $check = DomainStatusCheck::query()
            ->where('uuid', $uuid)
            ->where('admin_id', Auth::guard('admin')->id())
            ->where('source', 'disconnected')
            ->firstOrFail();

        if (! $check->isFinished()) {
            abort(409, 'Recheck is still running. Export is available after completion.');
        }

        $fileName = 'disconnected-recheck-'.$check->uuid.'.csv';

        return response()->streamDownload(function () use ($check) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Domain',
                'Category',
                'Previous Status',
                'New Status',
                'Code',
                'Message',
                'Probe Method',
                'Response Time (ms)',
                'Checked At',
            ]);

            foreach ($this->statusChecker->csvRowsForCheck($check) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
