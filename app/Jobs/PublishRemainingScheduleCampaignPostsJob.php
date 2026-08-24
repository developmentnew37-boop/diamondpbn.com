<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\ScheduleCampaignTargetCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Immediately dispatch remaining (non-success) schedule posts for publish.
 * Does NOT change schedule_at — original dates stay on details/report.
 */
class PublishRemainingScheduleCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public int $campaignId,
    ) {
        $this->onQueue('bulk_retry_scheduled_campaigns');
    }

    public function handle(ScheduleCampaignTargetCounterService $counters): void
    {
        $summary = self::publishRemaining($this->campaignId, $counters);

        Log::info('PublishRemainingScheduleCampaignPostsJob completed', [
            'campaign_id' => $this->campaignId,
            ...$summary,
        ]);
    }

    /**
     * @return array{queued: int, skipped: bool, reason: ?string}
     */
    public static function publishRemaining(int $campaignId, ScheduleCampaignTargetCounterService $counters): array
    {
        $campaign = ScheduleCampaign::find($campaignId);

        if (! $campaign) {
            return ['queued' => 0, 'skipped' => true, 'reason' => 'not_found'];
        }

        if (in_array((string) $campaign->status, ['paused', 'cancelled'], true)) {
            return ['queued' => 0, 'skipped' => true, 'reason' => 'paused_or_cancelled'];
        }

        $posts = ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $campaignId)
            ->whereIn('status', ['queued', 'failed', 'publishing'])
            ->where(function ($q) {
                $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
            })
            ->get();

        if ($posts->isEmpty()) {
            return ['queued' => 0, 'skipped' => true, 'reason' => 'none_remaining'];
        }

        $failedCount = 0;

        foreach ($posts as $post) {
            if ($post->status === 'failed') {
                $failedCount++;
            }

            // Keep schedule_at unchanged so UI/report still show original dates.
            $post->update([
                'status' => 'queued',
                'attempt_count' => 0,
                'last_error' => null,
                'next_retry_at' => null,
                'locked_at' => null,
                'lock_token' => null,
            ]);

            PublishScheduledCampaignPostJob::dispatch(
                $post->id,
                (int) ($post->dispatch_generation ?? 0)
            )->onQueue('scheduled_campaigns');
        }

        if ($failedCount > 0) {
            $counters->accountForFailedPostRetries($campaign, $failedCount);
        }

        $counters->syncCampaignFromPosts($campaign->fresh());

        return [
            'queued' => $posts->count(),
            'skipped' => false,
            'reason' => null,
        ];
    }
}
