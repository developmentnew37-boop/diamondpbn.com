<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Domain;
use App\Models\Admin\PendingDomain;
use App\Services\PendingDomainTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PendingDomainController extends Controller
{
    public function __construct(
        private readonly PendingDomainTransferService $transferService
    ) {}

    public function index(Request $request): View
    {
        $query = PendingDomain::with('webhookSecret');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('viewed')) {
            $query->where('viewed', $request->viewed === '1');
        }

        $pendingDomains = $query->orderBy('created_at', 'desc')->paginate(20);

        $pendingCount = PendingDomain::where('status', 'pending')->count();
        $unviewedCount = PendingDomain::where('status', 'pending')->where('viewed', false)->count();
        $existingInventoryCount = $this->transferService->countPendingExistingInInventory();
        $existingInventoryNames = $this->transferService->pendingNamesExistingInInventory();
        $newSitesCount = max(0, $pendingCount - $existingInventoryCount);

        return view('admin.domains.pending.index', compact(
            'pendingDomains',
            'pendingCount',
            'unviewedCount',
            'existingInventoryCount',
            'existingInventoryNames',
            'newSitesCount',
        ));
    }

    /**
     * Sync all pending domains that already exist in inventory (update API key, clear from pending).
     */
    public function syncExistingInventory(Request $request): RedirectResponse
    {
        $adminId = (int) Auth::guard('admin')->id();

        try {
            $result = $this->transferService->syncExistingPendingDomains(
                $adminId,
                'Synced API key with existing domain in inventory'
            );

            if ($result['synced'] === 0 && $result['failed'] === 0) {
                $message = $result['skipped_new'] > 0
                    ? 'No pending domains matched your inventory. Use Transfer Domains for new sites.'
                    : 'No pending domains to sync.';
            } else {
                $message = "Synced {$result['synced']} existing domain(s) — API keys updated and removed from pending.";

                if ($result['skipped_new'] > 0) {
                    $message .= " {$result['skipped_new']} new domain(s) left in pending (use Transfer Domains).";
                }

                if ($result['failed'] > 0) {
                    $message .= " {$result['failed']} failed.";
                }

                if ($result['synced'] > 0) {
                    $message .= ' Plugin connection checks are running in the background.';
                }
            }

            if (! empty($result['errors'])) {
                session()->flash('transfer_errors', $result['errors']);
            }

            Log::info('Pending domains synced with existing inventory', [
                'admin' => auth('admin')->user()->email,
                'result' => $result,
            ]);

            return redirect()
                ->route('admin.pending-domains.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Pending domain inventory sync failed', [
                'error' => $e->getMessage(),
                'admin' => auth('admin')->user()->email,
            ]);

            return redirect()
                ->route('admin.pending-domains.index')
                ->with('error', 'Sync failed: '.$e->getMessage());
        }
    }

    public function show(PendingDomain $pendingDomain): View
    {
        $pendingDomain->load('webhookSecret');
        $pendingDomain->markAsViewed();

        $inInventory = Domain::query()
            ->whereNormalizedName($pendingDomain->normalized_name)
            ->exists();

        return view('admin.domains.pending.show', compact('pendingDomain', 'inInventory'));
    }

    public function syncSingleInventory(PendingDomain $pendingDomain): RedirectResponse
    {
        if ($pendingDomain->status !== 'pending') {
            return redirect()
                ->back()
                ->with('error', 'This domain has already been processed');
        }

        $adminId = (int) Auth::guard('admin')->id();

        try {
            $result = $this->transferService->syncExistingPendingDomains(
                $adminId,
                'Synced API key with existing domain in inventory',
                true,
                [$pendingDomain->id]
            );

            if ($result['synced'] === 0) {
                return redirect()
                    ->back()
                    ->with('error', 'This domain is not in your inventory yet. Use Transfer to add it.');
            }

            return redirect()
                ->route('admin.pending-domains.index')
                ->with('success', 'API key synced and domain removed from pending.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Sync failed: '.$e->getMessage());
        }
    }

    public function reject(Request $request, PendingDomain $pendingDomain): RedirectResponse
    {
        if ($pendingDomain->status !== 'pending') {
            return redirect()
                ->back()
                ->with('error', 'This domain has already been processed');
        }

        $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $pendingDomain->reject($request->notes);

        Log::info('Pending domain rejected', [
            'pending_domain_id' => $pendingDomain->id,
            'domain_name' => $pendingDomain->domain_name,
            'notes' => $request->notes,
            'admin' => auth('admin')->user()->email,
        ]);

        return redirect()
            ->route('admin.pending-domains.index')
            ->with('success', 'Domain rejected');
    }

    public function destroy(PendingDomain $pendingDomain): RedirectResponse
    {
        $domainName = $pendingDomain->domain_name;
        $pendingDomain->delete();

        Log::info('Pending domain deleted', [
            'domain_name' => $domainName,
            'admin' => auth('admin')->user()->email,
        ]);

        return redirect()
            ->route('admin.pending-domains.index')
            ->with('success', 'Pending domain deleted');
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $request->validate([
            'domain_ids' => 'required|array',
            'domain_ids.*' => 'exists:pending_domains,id',
            'notes' => 'required|string|max:1000',
        ]);

        $rejectedCount = 0;

        foreach ($request->domain_ids as $id) {
            $pendingDomain = PendingDomain::find($id);

            if ($pendingDomain && $pendingDomain->status === 'pending') {
                $pendingDomain->reject($request->notes);
                $rejectedCount++;
            }
        }

        return redirect()
            ->route('admin.pending-domains.index')
            ->with('success', "Rejected {$rejectedCount} domain(s)");
    }
}
