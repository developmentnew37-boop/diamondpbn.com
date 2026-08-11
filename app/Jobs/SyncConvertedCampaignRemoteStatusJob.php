<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleCampaign;
use App\Services\ConvertedPostRemoteSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncConvertedCampaignRemoteStatusJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedPostJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'campaign_conversions';

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $scheduleCampaignId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        return 'Post Live→Dripfeed: Sync WordPress status — '.$this->dripfeedPostCampaignLabel($this->scheduleCampaignId);
    }

    public function handle(ConvertedPostRemoteSyncService $sync): void
    {
        $campaign = ScheduleCampaign::find($this->scheduleCampaignId);
        if (! $campaign || ! filled($campaign->converted_from_campaign_id)) {
            return;
        }

        $sync->syncCampaignPosts($campaign);
    }
}
