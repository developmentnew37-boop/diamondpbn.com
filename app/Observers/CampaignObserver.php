<?php

namespace App\Observers;

use App\Models\Admin\Campaign;
use Illuminate\Support\Facades\Cache;

class CampaignObserver
{
    /**
     * Handle the Campaign "created" event.
     */
    public function created(Campaign $campaign): void
    {
        if ($campaign->admin_id !== null) {
            $this->clearDashboardCache((int) $campaign->admin_id);
        }
    }

    /**
     * Handle the Campaign "updated" event.
     */
    public function updated(Campaign $campaign): void
    {
        if ($campaign->admin_id !== null) {
            $this->clearDashboardCache((int) $campaign->admin_id);
        }
    }

    /**
     * Handle the Campaign "deleted" event.
     */
    public function deleted(Campaign $campaign): void
    {
        if ($campaign->admin_id !== null) {
            $this->clearDashboardCache((int) $campaign->admin_id);
        }
    }

    /**
     * Clear dashboard cache for the admin
     */
    private function clearDashboardCache(int $adminId): void
    {
        Cache::forget('dashboard_data_'.$adminId.'_user');
        Cache::forget('dashboard_data_'.$adminId.'_super');
        Cache::forget('campaign_create_data_'.$adminId);
    }
}
