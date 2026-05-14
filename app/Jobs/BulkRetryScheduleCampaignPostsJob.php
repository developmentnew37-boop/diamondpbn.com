<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;

class BulkRetryScheduleCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param int[] $campaignIds
     */
    public function __construct(
        public array $campaignIds,
    ) {
        $this->onQueue('bulk_retry_scheduled_campaigns');
    }

    public function handle(): void
    {
        $totalRetried = 0;
        $skipped = 0;

        foreach ($this->campaignIds as $campaignId) {
            $campaign = ScheduleCampaign::find($campaignId);

            if (!$campaign) {
                Log::warning('BulkRetryScheduleCampaignPostsJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;
                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetryScheduleCampaignPostsJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;
                continue;
            }

            $failedPosts = ScheduleCampaignPost::where('schedule_campaign_id', $campaignId)
                ->where('status', 'failed')
                ->get();

            foreach ($failedPosts as $post) {
                $post->update([
                    'status'       => 'queued',
                    'last_error'   => null,
                    'next_retry_at' => null,
                    'locked_at'    => null,
                    'lock_token'   => null,
                ]);

                PublishScheduledCampaignPostJob::dispatch($post->id)->onQueue('scheduled_campaigns');
                $totalRetried++;
            }
        }

        Log::info('BulkRetryScheduleCampaignPostsJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'posts_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
        ]);
    }
}
