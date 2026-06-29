<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\PendingDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PendingDomainController extends Controller
{
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

        return view('admin.domains.pending.index', compact('pendingDomains', 'pendingCount', 'unviewedCount'));
    }

    public function show(PendingDomain $pendingDomain): View
    {
        $pendingDomain->load('webhookSecret');
        $pendingDomain->markAsViewed();

        return view('admin.domains.pending.show', compact('pendingDomain'));
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
