<?php

namespace App\Jobs;

use App\Models\Admin\WpScheduledCampaign;
use App\Models\Admin\WpScheduledCampaignPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkRetryWpScheduledCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public int $campaignId,
        public bool $includeStuck = true,
    ) {
        $this->onQueue('bulk_retry');
    }

    public static function retryableQuery(int $campaignId, bool $includeStuck = true): Builder
    {
        $query = WpScheduledCampaignPost::query()->where('wp_scheduled_campaign_id', $campaignId);

        if ($includeStuck) {
            return $query->whereIn('status', ['queued', 'failed', 'publishing']);
        }

        return $query->where('status', 'failed');
    }

    public function handle(): void
    {
        $campaign = WpScheduledCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning('BulkRetryWpScheduledCampaignPostsJob: Campaign not found', ['campaign_id' => $this->campaignId]);

            return;
        }

        if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
            Log::info('BulkRetryWpScheduledCampaignPostsJob: Skipping paused/cancelled campaign', ['campaign_id' => $this->campaignId]);

            return;
        }

        $posts = self::retryableQuery($this->campaignId, $this->includeStuck)->get();
        $retried = 0;

        foreach ($posts as $post) {
            $post->update([
                'status' => 'queued',
                'attempt_count' => 0,
                'last_error' => null,
                'next_retry_at' => null,
                'locked_at' => null,
                'lock_token' => null,
            ]);

            PublishWpScheduledPostJob::dispatch($post->id)->onQueue('wp_scheduled_campaigns');
            $retried++;
        }

        Log::info('BulkRetryWpScheduledCampaignPostsJob completed', [
            'campaign_id' => $this->campaignId,
            'posts_retried' => $retried,
            'include_stuck' => $this->includeStuck,
        ]);
    }
}
