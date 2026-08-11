<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\PostStatusApiService;
use App\Services\ScheduleCampaignTargetCounterService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DraftConvertedLivePostsJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedPostJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'campaign_conversions';

    public int $tries = 3;

    public int $timeout = 3600;

    /**
     * @param  int[]  $schedulePostIds
     */
    public function __construct(
        public array $schedulePostIds,
        public int $scheduleCampaignId,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        $count = count($this->schedulePostIds);
        $campaign = $this->dripfeedPostCampaignLabel($this->scheduleCampaignId);
        $sample = $count <= 3
            ? ' ['.implode(',', $this->schedulePostIds).']'
            : ' [posts '.implode(',', array_slice($this->schedulePostIds, 0, 3)).',…]';

        return "Post Live→Dripfeed: Draft {$count} post(s) — {$campaign}{$sample}";
    }

    public function handle(PostStatusApiService $api, ScheduleCampaignTargetCounterService $counters): void
    {
        $campaign = ScheduleCampaign::find($this->scheduleCampaignId);
        if (! $campaign) {
            return;
        }

        $runDate = $campaign->conversion_run_date
            ? Carbon::parse($campaign->conversion_run_date)->startOfDay()
            : now()->startOfDay();

        $posts = ScheduleCampaignPost::query()
            ->with(['campaignDomain.domain', 'campaign'])
            ->whereIn('id', $this->schedulePostIds)
            ->where('is_converted_live', true)
            ->get();

        foreach ($posts as $post) {
            if ($post->conversion_phase !== 'pending_draft' && $post->conversion_phase !== 'failed') {
                continue;
            }

            if (! filled($post->remote_id)) {
                $this->markFailed($post, 'Missing remote_id for converted post.', $counters);

                continue;
            }

            $domain = $post->campaignDomain?->domain;
            if (! $domain || ! filled($domain->api_key)) {
                $this->markFailed($post, 'Domain or API key missing.', $counters);

                continue;
            }

            $result = $api->draftPost((string) $domain->name, (string) $domain->api_key, (string) $post->remote_id);

            $post->last_conversion_attempt_at = now();

            if (! $result['ok']) {
                $this->markFailed($post, $result['message'], $counters);

                continue;
            }

            $post->conversion_phase = 'drafted';
            $post->remote_status = 'draft';
            $post->last_conversion_error = null;
            $post->save();

            $slotDate = $post->schedule_at ? Carbon::parse($post->schedule_at)->startOfDay() : $runDate;
            if ($slotDate->lte($runDate)) {
                ApplyConvertedPostScheduleJob::dispatch($post->id)->onQueue(ApplyConvertedPostScheduleJob::QUEUE);
            }
        }

        $this->refreshPipelineStatus($campaign);
        $counters->syncCampaignFromPosts($campaign);
    }

    private function markFailed(ScheduleCampaignPost $post, string $message, ScheduleCampaignTargetCounterService $counters): void
    {
        $post->conversion_phase = 'failed';
        $post->last_conversion_error = $message;
        $post->last_conversion_attempt_at = now();
        $post->status = 'failed';
        $post->last_error = $message;
        $post->save();

        if ($post->campaign) {
            $counters->syncCampaignFromPosts($post->campaign);
        }
    }

    private function refreshPipelineStatus(ScheduleCampaign $campaign): void
    {
        $pending = ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $campaign->id)
            ->where('is_converted_live', true)
            ->where('conversion_phase', 'pending_draft')
            ->count();

        if ($pending === 0) {
            $failed = ScheduleCampaignPost::query()
                ->where('schedule_campaign_id', $campaign->id)
                ->where('is_converted_live', true)
                ->where('conversion_phase', 'failed')
                ->count();

            $campaign->update([
                'conversion_pipeline_status' => $failed > 0 ? 'semi_failed' : 'scheduling',
            ]);
        }
    }
}
