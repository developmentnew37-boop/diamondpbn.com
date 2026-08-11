<?php

namespace App\Services;

use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Models\Admin\LocalClientBillLine;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Support\BillableCampaignRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocalClientDeletionService
{
    public function delete(LocalClient $client): void
    {
        DB::transaction(function () use ($client) {
            $clientId = (int) $client->id;

            foreach (BillableCampaignRegistry::TYPES as $entry) {
                $modelClass = $entry['class'];
                $model = new $modelClass;

                if (! Schema::hasTable($model->getTable())) {
                    continue;
                }

                $modelClass::query()
                    ->where('local_client_id', $clientId)
                    ->update(['local_client_id' => null]);
            }

            if (Schema::hasTable('local_client_payment_events')) {
                LocalClientPaymentEvent::query()
                    ->where('local_client_id', $clientId)
                    ->delete();
            }

            if (Schema::hasTable('local_client_bill_lines')) {
                LocalClientBillLine::query()
                    ->where('local_client_id', $clientId)
                    ->delete();
            }

            if (Schema::hasTable('local_client_billing_periods')) {
                LocalClientBillingPeriod::query()
                    ->where('local_client_id', $clientId)
                    ->delete();
            }

            if (Schema::hasTable('local_client_domain_category_prices')) {
                LocalClientDomainCategoryPrice::query()
                    ->where('local_client_id', $clientId)
                    ->delete();
            }

            $client->delete();
        });
    }
}
