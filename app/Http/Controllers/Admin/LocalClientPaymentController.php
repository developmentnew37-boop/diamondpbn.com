<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LocalClientCampaignInvoiceService;
use App\Services\LocalClientPaymentService;
use App\Support\BillableCampaignRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocalClientPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can.create.campaigns');
    }

    public function update(
        Request $request,
        string $billableType,
        int $id,
        LocalClientPaymentService $paymentService,
    ) {
        $validated = $request->validate([
            'billing_payment_status' => 'required|in:paid,unpaid',
            'billing_payment_note' => 'nullable|string|max:500',
        ]);

        $campaign = BillableCampaignRegistry::resolve($billableType, $id);
        $paymentService->toggle(
            $campaign,
            $validated['billing_payment_status'],
            Auth::guard('admin')->user(),
            $validated['billing_payment_note'] ?? null,
        );

        return back()->with('cus__success', 'Payment status updated.');
    }

    public function invoice(
        string $billableType,
        int $id,
        LocalClientCampaignInvoiceService $invoiceService,
    ) {
        $campaign = BillableCampaignRegistry::resolve($billableType, $id);
        $admin = Auth::guard('admin')->user();

        if (! $admin->isSuperAdmin() && (int) $campaign->admin_id !== (int) $admin->id) {
            abort(403);
        }

        return $invoiceService->downloadPdf($campaign);
    }
}
