<?php

namespace App\Observers;

use App\Services\LocalClientBillingCleanupService;
use Illuminate\Database\Eloquent\Model;

class BillableCampaignBillingObserver
{
    public function deleting(Model $campaign): void
    {
        app(LocalClientBillingCleanupService::class)->onBillableDeleting($campaign);
    }
}
