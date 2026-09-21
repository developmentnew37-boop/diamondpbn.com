<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\ScheduleCampaignTargetCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkRetryScheduleCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    private const CHUNK_SIZE = 200;

    /**
     * @param  int[]  $campaignIds
     */
    public function __construct(
        public array $campaignIds,
        public bool $includeStuck = false,
    ) {
        $this->onQueue('bulk_retry_scheduled_campaigns');
    }

    public static function retryableQuery(int $campaignId, bool $includeStuck = false): Builder
    {
        $query = ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $campaignId)
            ->where(function ($q) {
                $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
            });

        if ($includeStuck) {
            return $query->whereIn('status', ['queued', 'failed', 'publishing']);
        }

        return $query->where('status', 'failed');
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

            if ($this->includeStuck) {
                $failedPosts = self::retryableQuery((int) $campaignId, true)->get();
            } else {
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
            }

            $failedCount = $failedPosts->count();
            if ($failedCount < 1) {
                continue;
            }

            $draftRetryIds = [];
            $statusFailedCount = 0;
            $normalRetryIds = [];

            foreach ($failedPosts as $post) {
                if ($post->status === 'failed') {
                    $statusFailedCount++;
                }

                if ($post->is_converted_live || $isConvertedCampaign) {
                    $this->retryConvertedPost($post, $draftRetryIds);
                    $totalRetried++;

                    continue;
                }

                $normalRetryIds[] = (int) $post->id;
            }

            $totalRetried += $this->resetAndDispatchNormalPosts($normalRetryIds);

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
     * @param  list<int>  $postIds
     */
    private function resetAndDispatchNormalPosts(array $postIds): int
    {
        if ($postIds === []) {
            return 0;
        }

        $dispatched = 0;

        foreach (array_chunk($postIds, self::CHUNK_SIZE) as $chunk) {
            ScheduleCampaignPost::query()
                ->whereIn('id', $chunk)
                ->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'dispatch_generation' => DB::raw('dispatch_generation + 1'),
                    'updated_at' => now(),
                ]);

            $posts = ScheduleCampaignPost::query()
                ->whereIn('id', $chunk)
                ->get(['id', 'dispatch_generation']);

            foreach ($posts as $post) {
                PublishScheduledCampaignPostJob::dispatch((int) $post->id, (int) ($post->dispatch_generation ?? 0))
                    ->onQueue('scheduled_campaigns');
                $dispatched++;
            }
        }

        return $dispatched;
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
