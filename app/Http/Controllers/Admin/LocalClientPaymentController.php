<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Campaign;
use App\Services\LocalClientBillingService;
use App\Services\LocalClientCampaignInvoiceService;
use App\Services\LocalClientPaymentService;
use App\Support\BillableCampaignRegistry;
use App\Support\CurrencyFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

    public function updateClient(
        Request $request,
        string $billableType,
        int $id,
        LocalClientBillingService $billingService,
    ) {
        $validated = $request->validate([
            'local_client_id' => 'nullable|integer|exists:local_clients,id',
            'billing_currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyFormatter::supportedCodes())],
        ]);

        $campaign = BillableCampaignRegistry::resolve($billableType, $id);
        $this->assertBillableTypeMatches($billableType, $campaign);

        $admin = Auth::guard('admin')->user();
        if (! $admin->isSuperAdmin() && (int) $campaign->admin_id !== (int) $admin->id) {
            abort(403);
        }

        $clientId = isset($validated['local_client_id']) && $validated['local_client_id'] !== ''
            ? (int) $validated['local_client_id']
            : null;

        $billingService->syncClientOnCampaign(
            $campaign,
            $clientId,
            $validated['billing_currency'] ?? null,
        );

        $message = $clientId
            ? 'Client billing updated.'
            : 'Client billing removed.';

        return back()
            ->with('cus__success', $message)
            ->with('edit_campaign_tab', 'local_client')
            ->with('edit_sidebar_campaign_tab', 'local_client')
            ->with('edit_hidden_link_campaign_tab', 'local_client')
            ->with('edit_schedule_campaign_tab', 'local_client')
            ->with('edit_schedule_sidebar_campaign_tab', 'local_client');
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

    private function assertBillableTypeMatches(string $billableType, object $campaign): void
    {
        if ($campaign instanceof Campaign) {
            $isSticky = (bool) ($campaign->is_sticky_campaign ?? false);
            if ($billableType === 'sticky_campaign' && ! $isSticky) {
                abort(404);
            }
            if ($billableType === 'campaign' && $isSticky) {
                abort(404);
            }
        }
    }
}
