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

class ApplyConvertedPostScheduleJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedPostJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'campaign_conversions';

    public int $tries = 5;

    public int $timeout = 300;

    public function __construct(public int $schedulePostId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function displayName(): string
    {
        $post = ScheduleCampaignPost::query()
            ->with('campaign:id,campaign_no')
            ->find($this->schedulePostId);

        $campaignLabel = $post?->campaign
            ? $this->dripfeedPostCampaignLabel((int) $post->schedule_campaign_id)
            : 'unknown post campaign';

        $remote = $post?->remote_id ? " WP#{$post->remote_id}" : '';

        return "Post Live→Dripfeed: Publish & date — {$campaignLabel} (schedule post #{$this->schedulePostId}{$remote})";
    }

    public function backoff(): array
    {
        return [60, 120, 300, 600, 900];
    }

    public function handle(PostStatusApiService $api, ScheduleCampaignTargetCounterService $counters): void
    {
        $post = ScheduleCampaignPost::query()
            ->with(['campaign', 'campaignDomain.domain'])
            ->find($this->schedulePostId);

        if (! $post || ! $post->is_converted_live) {
            return;
        }

        if (in_array($post->conversion_phase, ['published', 'scheduled_publish'], true) && $post->status === 'success') {
            return;
        }

        if (! in_array($post->conversion_phase, ['drafted', 'scheduled_publish'], true)) {
            return;
        }

        if (! filled($post->remote_id)) {
            $this->failPost($post, 'Missing remote_id.', $counters);

            return;
        }

        $domain = $post->campaignDomain?->domain;
        if (! $domain || ! filled($domain->api_key)) {
            $this->failPost($post, 'Domain or API key missing.', $counters);

            return;
        }

        $campaign = $post->campaign;
        $runDate = $campaign?->conversion_run_date
            ? Carbon::parse($campaign->conversion_run_date)->startOfDay()
            : now()->startOfDay();

        $slotDate = $post->schedule_at
            ? Carbon::parse($post->schedule_at)->startOfDay()
            : $runDate;

        if ($slotDate->gt(now()->startOfDay())) {
            return;
        }

        // Slot is due (today or earlier): go live as publish, not WordPress "future".
        // Use the slot calendar date with a timestamp that is not in the future on the server.
        $postDateStr = $slotDate->format('Y-m-d').' '.now()->format('H:i:s');
        $targetAt = Carbon::parse($postDateStr);
        if ($targetAt->gt(now())) {
            $postDateStr = now()->subMinute()->format('Y-m-d H:i:s');
        }

        $useScheduleTime = false;

        $publish = $api->publishPost((string) $domain->name, (string) $domain->api_key, (string) $post->remote_id);
        $post->last_conversion_attempt_at = now();

        if (! $publish['ok']) {
            $this->failPost($post, $publish['message'], $counters);

            return;
        }

        $update = $api->updatePostDate(
            (string) $domain->name,
            (string) $domain->api_key,
            (string) $post->remote_id,
            $postDateStr,
            $useScheduleTime,
        );

        if (! $update['ok']) {
            $this->failPost($post, 'Published but post date update failed: '.$update['message'], $counters);

            return;
        }

        $snapshot = $api->fetchPost((string) $domain->name, (string) $domain->api_key, (string) $post->remote_id);
        if (! $snapshot['ok']) {
            $this->failPost($post, 'Publish/update sent but remote verify failed: '.$snapshot['message'], $counters);

            return;
        }

        $remoteStatus = $snapshot['status'];
        if ($remoteStatus === 'draft') {
            $this->failPost($post, 'Remote post is still draft after conversion (link will 404 until published).', $counters);

            return;
        }

        if (! in_array($remoteStatus, ['publish', 'future'], true)) {
            $this->failPost($post, 'Unexpected remote post status after conversion: '.($remoteStatus ?? 'unknown'), $counters);

            return;
        }

        $post->remote_status = $remoteStatus;
        if ($snapshot['remote_url']) {
            $post->remote_url = $snapshot['remote_url'];
        } else {
            $fromUpdate = $api->extractFromMutationResponse($update['body'] ?? null);
            if ($fromUpdate['remote_url']) {
                $post->remote_url = $fromUpdate['remote_url'];
            }
        }

        $post->conversion_phase = $remoteStatus === 'future' ? 'scheduled_publish' : 'published';
        $post->status = 'success';
        $post->published_at = $remoteStatus === 'publish' ? now() : null;
        $post->last_conversion_error = null;
        $post->last_error = null;
        $post->remote_response = $snapshot['body'];
        $post->save();

        if ($campaign) {
            $counters->syncCampaignFromPosts($campaign);
        }
    }

    private function failPost(ScheduleCampaignPost $post, string $message, ScheduleCampaignTargetCounterService $counters): void
    {
        $post->conversion_phase = 'failed';
        $post->status = 'failed';
        $post->last_conversion_error = $message;
        $post->last_error = $message;
        $post->last_conversion_attempt_at = now();
        $post->save();

        $campaign = $post->campaign ?? ScheduleCampaign::find($post->schedule_campaign_id);
        if ($campaign) {
            $counters->syncCampaignFromPosts($campaign);
        }
    }
}
