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

class BulkRetryScheduleCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param  int[]  $campaignIds
     */
    public function __construct(
        public array $campaignIds,
    ) {
        $this->onQueue('bulk_retry_scheduled_campaigns');
    }

    public function handle(ScheduleCampaignTargetCounterService $counters): void
    {
        $totalRetried = 0;
        $skipped = 0;

        foreach ($this->campaignIds as $campaignId) {
            $campaign = ScheduleCampaign::find($campaignId);

            if (! $campaign) {
                Log::warning('BulkRetryScheduleCampaignPostsJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetryScheduleCampaignPostsJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            $isConvertedCampaign = filled($campaign->converted_from_campaign_id);

            $failedPosts = ScheduleCampaignPost::where('schedule_campaign_id', $campaignId)
                ->where(function ($q) use ($isConvertedCampaign) {
                    $q->where('status', 'failed');
                    if ($isConvertedCampaign) {
                        $q->orWhere(function ($q2) {
                            $q2->where('is_converted_live', true)
                                ->where('conversion_phase', 'failed');
                        });
                    }
                })
                ->get()
                ->unique('id')
                ->values();

            $failedCount = $failedPosts->count();
            if ($failedCount < 1) {
                continue;
            }

            $draftRetryIds = [];
            $statusFailedCount = 0;

            foreach ($failedPosts as $post) {
                if ($post->status === 'failed') {
                    $statusFailedCount++;
                }

                if ($post->is_converted_live || $isConvertedCampaign) {
                    $this->retryConvertedPost($post, $draftRetryIds);
                    $totalRetried++;

                    continue;
                }

                $post->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                ]);

                PublishScheduledCampaignPostJob::dispatch($post->id, (int) ($post->dispatch_generation ?? 0))->onQueue('scheduled_campaigns');
                $totalRetried++;
            }

            if ($draftRetryIds !== []) {
                DraftConvertedLivePostsJob::dispatch($draftRetryIds, (int) $campaign->id)
                    ->onQueue(DraftConvertedLivePostsJob::QUEUE);
            }

            if ($statusFailedCount > 0) {
                $counters->accountForFailedPostRetries($campaign, $statusFailedCount);
            }
            $counters->syncCampaignFromPosts($campaign->fresh());
        }

        Log::info('BulkRetryScheduleCampaignPostsJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'posts_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
        ]);
    }

    /**
     * Mirror CampaignPostConversionController::retryConversionPost.
     *
     * @param  list<int>  $draftRetryIds
     */
    private function retryConvertedPost(ScheduleCampaignPost $post, array &$draftRetryIds): void
    {
        if (in_array((string) $post->conversion_phase, ['pending_draft', 'failed'], true)) {
            $post->update([
                'conversion_phase' => 'pending_draft',
                'status' => 'queued',
                'attempt_count' => 0,
                'last_error' => null,
                'last_conversion_error' => null,
                'next_retry_at' => null,
                'locked_at' => null,
                'lock_token' => null,
            ]);
            $draftRetryIds[] = (int) $post->id;

            return;
        }

        $post->update([
            'conversion_phase' => 'drafted',
            'status' => 'queued',
            'attempt_count' => 0,
            'last_error' => null,
            'last_conversion_error' => null,
            'next_retry_at' => null,
            'locked_at' => null,
            'lock_token' => null,
        ]);

        ApplyConvertedPostScheduleJob::dispatch((int) $post->id)
            ->onQueue(ApplyConvertedPostScheduleJob::QUEUE);
    }
}
