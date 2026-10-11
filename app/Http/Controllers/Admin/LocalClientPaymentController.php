<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Campaign;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Models\Admin\ScheduleCampaign;
use App\Services\LocalClientBillingService;
use App\Services\LocalClientCampaignInvoiceService;
use App\Services\LocalClientPaymentService;
use App\Support\BillableCampaignRegistry;
use App\Support\CurrencyFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'billing_payment_status' => 'required|in:paid,unpaid,semi_paid',
            'billing_payment_note' => 'nullable|string|max:500',
        ]);

        $campaign = BillableCampaignRegistry::resolve($billableType, $id);

        try {
            $paymentService->toggle(
                $campaign,
                $validated['billing_payment_status'],
                Auth::guard('admin')->user(),
                $validated['billing_payment_note'] ?? null,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Payment status could not be updated.';

            return back()->with('cus__error', $message)->withInput();
        }

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

    public function syncBilling(
        string $billableType,
        int $id,
        LocalClientBillingService $billingService,
    ) {
        $campaign = BillableCampaignRegistry::resolve($billableType, $id);
        $this->assertBillableTypeMatches($billableType, $campaign);

        $admin = Auth::guard('admin')->user();
        if (! $admin->isSuperAdmin() && (int) $campaign->admin_id !== (int) $admin->id) {
            abort(403);
        }

        if (! $campaign->local_client_id) {
            return back()->with('cus__error', 'This campaign has no local client billing to sync.');
        }

        $clientId = (int) $campaign->local_client_id;
        $currency = $campaign->billing_currency;
        $oldStatus = $campaign->billing_payment_status ?? 'unpaid';

        $billingService->syncClientOnCampaign($campaign, $clientId, $currency);
        $campaign->refresh();

        $currencyCode = $campaign->billing_currency ?? 'USD';
        $newFormatted = CurrencyFormatter::format($campaign->billing_total, $currencyCode);
        $newStatus = $campaign->billing_payment_status ?? 'unpaid';

        if (in_array($oldStatus, ['paid', 'semi_paid'], true)) {
            $due = LocalClientBillingService::balanceDue(
                $campaign->billing_total,
                $campaign->billing_amount_paid,
                $newStatus,
            );

            LocalClientPaymentEvent::create([
                'billable_type' => $campaign::class,
                'billable_id' => $campaign->id,
                'local_client_id' => $campaign->local_client_id,
                'campaign_no' => $campaign->campaign_no ?? null,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note' => $newStatus === 'semi_paid'
                    ? sprintf(
                        'Billing synced. Already paid %s. Due now %s.',
                        CurrencyFormatter::format($campaign->billing_amount_paid, $currencyCode),
                        CurrencyFormatter::format($due, $currencyCode),
                    )
                    : sprintf('Billing synced. New total %s. Status remains paid.', $newFormatted),
                'admin_id' => $admin->id,
                'created_at' => now(),
            ]);

            $message = $newStatus === 'semi_paid'
                ? sprintf(
                    'Billing synced. New total: %s. Already paid: %s. Due now: %s.',
                    $newFormatted,
                    CurrencyFormatter::format($campaign->billing_amount_paid, $currencyCode),
                    CurrencyFormatter::format($due, $currencyCode),
                )
                : "Billing synced. New total: {$newFormatted}. Status remains paid.";

            return back()->with('cus__success', $message);
        }

        return back()->with('cus__success', "Billing synced. New total: {$newFormatted}");
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

        if ($campaign instanceof ScheduleCampaign) {
            $isSticky = (bool) ($campaign->is_sticky_campaign ?? false);
            if ($billableType === 'schedule_sticky_campaign' && ! $isSticky) {
                abort(404);
            }
            if ($billableType === 'schedule_campaign' && $isSticky) {
                abort(404);
            }
        }
    }
}
