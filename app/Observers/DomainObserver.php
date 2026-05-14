<?php

namespace App\Observers;

use App\Models\Admin\Domain;
use Illuminate\Support\Facades\Cache;

class DomainObserver
{
    /**
     * Handle the Domain "created" event.
     */
    public function created(Domain $domain): void
    {
        $this->clearCaches($domain->admin_id);
    }

    /**
     * Handle the Domain "updated" event.
     */
    public function updated(Domain $domain): void
    {
        $this->clearCaches($domain->admin_id);
    }

    /**
     * Handle the Domain "deleted" event.
     */
    public function deleted(Domain $domain): void
    {
        $this->clearCaches($domain->admin_id);
    }

    /**
     * Clear relevant caches
     */
    private function clearCaches(int $adminId): void
    {
        Cache::forget('dashboard_data_' . $adminId . '_user');
        Cache::forget('dashboard_data_' . $adminId . '_super');
        Cache::forget('campaign_create_data_' . $adminId);
    }
}
