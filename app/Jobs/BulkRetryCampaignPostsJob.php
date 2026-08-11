<?php

namespace App\Jobs;

use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkRetryCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param  int[]  $campaignIds
     */
    public function __construct(
        public array $campaignIds,
    ) {
        $this->onQueue('bulk_retry');
    }

    public function handle(): void
    {
        $totalRetried = 0;
        $skipped = 0;

        foreach ($this->campaignIds as $campaignId) {
            $campaign = Campaign::find($campaignId);

            if (! $campaign) {
                Log::warning('BulkRetryCampaignPostsJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetryCampaignPostsJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            $failedPosts = CampaignPost::where('campaign_id', $campaignId)
                ->where('status', 'failed')
                ->whereIn('delivery_state', [
                    CampaignPost::DELIVERY_NOT_ATTEMPTED,
                    CampaignPost::DELIVERY_REMOTE_ABSENT,
                ])
                ->whereNull('remote_id')
                ->whereNull('remote_url')
                ->whereNull('published_at')
                ->whereNull('locked_at')
                ->whereNull('locked_until')
                ->whereNull('lock_token')
                ->get();

            foreach ($failedPosts as $post) {
                $post->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'last_error' => null,
                    'last_failure_code' => null,
                ]);

                PublishCampaignPostJob::dispatch($post->id, $post->dispatch_generation)
                    ->onQueue('campaigns');
                $totalRetried++;
            }
        }

        Log::info('BulkRetryCampaignPostsJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'posts_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
        ]);
    }
}
