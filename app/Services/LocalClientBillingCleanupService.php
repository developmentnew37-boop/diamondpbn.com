<?php

namespace App\Services;

use App\Models\Admin\LocalClientBillLine;
use Illuminate\Database\Eloquent\Model;

class LocalClientBillingCleanupService
{
    public function onBillableDeleting(Model $campaign): void
    {
        if (! $campaign->getAttribute('local_client_id') && $campaign->getAttribute('billing_total') === null) {
            return;
        }

        LocalClientBillLine::query()
            ->where('billable_type', $campaign::class)
            ->where('billable_id', $campaign->getKey())
            ->delete();
    }
}
