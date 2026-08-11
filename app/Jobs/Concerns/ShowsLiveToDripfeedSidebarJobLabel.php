<?php

namespace App\Jobs\Concerns;

use App\Models\Admin\ScheduleSidebarCampaign;

trait ShowsLiveToDripfeedSidebarJobLabel
{
    protected function dripfeedSidebarCampaignLabel(int $scheduleSidebarCampaignId): string
    {

        $no = ScheduleSidebarCampaign::query()->whereKey($scheduleSidebarCampaignId)->value('campaign_no');

        return $no ?: ('sidebar campaign #'.$scheduleSidebarCampaignId);

    }
}
