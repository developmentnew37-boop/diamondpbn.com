<?php

namespace App\Jobs\Concerns;

use App\Models\Admin\ScheduleCampaign;

trait ShowsLiveToDripfeedPostJobLabel
{
    protected function dripfeedPostCampaignLabel(int $scheduleCampaignId): string
    {

        $no = ScheduleCampaign::query()->whereKey($scheduleCampaignId)->value('campaign_no');

        return $no ?: ('post campaign #'.$scheduleCampaignId);

    }
}
