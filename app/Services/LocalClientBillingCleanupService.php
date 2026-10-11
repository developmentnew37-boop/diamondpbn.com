<?php

namespace App\Services;

use App\Models\Admin\LocalClientBillLine;
use App\Models\Admin\LocalClientDeletedCampaignBilling;
use App\Support\BillableCampaignRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class LocalClientBillingCleanupService
{
    public function onBillableDeleting(Model $campaign): void
    {
        if (! $campaign->getAttribute('local_client_id') && $campaign->getAttribute('billing_total') === null) {
            return;
        }

        $this->archivePaidBilling($campaign);

        if (! Schema::hasTable('local_client_bill_lines')) {
            return;
        }

        LocalClientBillLine::query()
            ->where('billable_type', $campaign::class)
            ->where('billable_id', $campaign->getKey())
            ->delete();
    }

    private function archivePaidBilling(Model $campaign): void
    {
        if (! Schema::hasTable('local_client_deleted_campaign_billings')) {
            return;
        }

        $clientId = $campaign->getAttribute('local_client_id');
        $status = (string) ($campaign->getAttribute('billing_payment_status') ?? '');

        if (! $clientId || $campaign->getAttribute('billing_total') === null) {
            return;
        }

        if (! in_array($status, ['paid', 'semi_paid'], true)) {
            return;
        }

        LocalClientDeletedCampaignBilling::query()->updateOrCreate(
            [
                'billable_type' => $campaign::class,
                'billable_id' => $campaign->getKey(),
            ],
            [
                'local_client_id' => $clientId,
                'campaign_type' => BillableCampaignRegistry::typeForModel($campaign),
                'campaign_no' => $campaign->getAttribute('campaign_no'),
                'billing_total' => $campaign->getAttribute('billing_total'),
                'billing_amount_paid' => $campaign->getAttribute('billing_amount_paid'),
                'billing_currency' => $campaign->getAttribute('billing_currency') ?: 'USD',
                'billing_payment_status' => $status,
                'billing_paid_at' => $campaign->getAttribute('billing_paid_at'),
                'campaign_created_at' => $campaign->getAttribute('created_at'),
            ]
        );
    }
}
