<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Services\ConvertedSidebarRemoteSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncConvertedSidebarCampaignRemoteStatusJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedSidebarJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'sidebar_campaign_conversions';

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $scheduleSidebarCampaignId)
    {

        $this->onQueue(self::QUEUE);

    }

    public function displayName(): string
    {

        return 'Sidebar Live→Schedule: Sync blogroll status — '.$this->dripfeedSidebarCampaignLabel($this->scheduleSidebarCampaignId);

    }

    public function handle(ConvertedSidebarRemoteSyncService $sync): void
    {

        $campaign = ScheduleSidebarCampaign::find($this->scheduleSidebarCampaignId);

        if (! $campaign || ! filled($campaign->converted_from_sidebar_campaign_id)) {

            return;

        }

        $sync->syncCampaignTasks($campaign);

    }
}
