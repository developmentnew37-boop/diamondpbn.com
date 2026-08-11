<?php

namespace App\Jobs;

use App\Models\Admin\CampaignPost;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\CampaignPostContentBuilder;
use App\Services\ScheduleCampaignTargetCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RepublishConvertedSchedulePostAfterDomainReplaceJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedPostJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $schedulePostId,
        public int $dispatchGeneration = 0,
    ) {
        $this->onQueue(DraftConvertedLivePostsJob::QUEUE);
    }

    public function displayName(): string
    {
        return "Post Live→Dripfeed: Republish after domain replace — schedule post #{$this->schedulePostId}";
    }

    public function handle(ScheduleCampaignTargetCounterService $counters): void
    {
        $post = ScheduleCampaignPost::query()
            ->with(['campaignDomain.domain', 'campaign'])
            ->find($this->schedulePostId);

        if (! $post || ! $post->is_converted_live) {
            return;
        }

        if ((int) $post->dispatch_generation !== $this->dispatchGeneration) {
            return;
        }

        $source = CampaignPost::query()
            ->with(['campaignArticle.article'])
            ->find($post->source_campaign_post_id);

        if (! $source) {
            $this->markFailed($post, 'Source live post not found for republish.', $counters);

            return;
        }

        $domain = $post->campaignDomain?->domain;
        if (! $domain || ! filled($domain->api_key)) {
            $this->markFailed($post, 'Replacement domain or API key missing.', $counters);

            return;
        }

        try {
            [$title, $content] = CampaignPostContentBuilder::build($source);

            $title = cleanUtf8($title, [
                'context' => 'wp_api_post',
                'post_id' => $post->id,
                'field' => 'title',
            ]);

            $content = cleanUtf8($content, [
                'context' => 'wp_api_post',
                'post_id' => $post->id,
                'field' => 'content',
            ]);

            if (isRtlText($content) || isRtlText($title)) {
                $content = wrapRtlContent($content, $title);
            }

            $base = trim((string) $domain->name);
            if (! preg_match('~^https?://~i', $base)) {
                $base = 'https://'.$base;
            }

            $endpoint = rtrim($base, '/').'/wp-json/external/v1/posts/create';
            $payload = [
                'title' => $title,
                'content' => $content,
                'status' => 'publish',
                'post_type' => 'post',
                'api_key' => (string) $domain->api_key,
                'is_sticky' => (bool) ($post->campaign?->is_sticky_campaign ?? false),
            ];

            $response = Http::withoutVerifying()
                ->timeout(180)
                ->acceptJson()
                ->contentType('application/json; charset=utf-8')
                ->withBody(safeJsonEncode($payload), 'application/json; charset=utf-8')
                ->post($endpoint);

            if (! $response->successful()) {
                throw new \RuntimeException("WP API failed ({$response->status()}): ".$response->body());
            }

            $json = $response->json();
            if (! is_array($json)) {
                throw new \RuntimeException('WP API returned non-JSON response.');
            }

            $remoteId = $json['post_id'] ?? $json['data']['post_id'] ?? null;
            if (! filled($remoteId)) {
                throw new \RuntimeException('WP API did not return post_id.');
            }

            $post->forceFill([
                'remote_id' => (string) $remoteId,
                'remote_title' => $title,
                'remote_url' => $json['remote_url'] ?? $json['link'] ?? $json['permalink'] ?? $json['url'] ?? null,
                'remote_status' => $json['status'] ?? 'publish',
                'http_status' => $response->status(),
                'remote_response' => $json,
                'published_at' => now(),
                'conversion_phase' => 'pending_draft',
                'status' => 'queued',
                'last_error' => null,
                'last_conversion_error' => null,
                'last_conversion_attempt_at' => null,
            ])->save();

            DraftConvertedLivePostsJob::dispatch([$post->id], (int) $post->schedule_campaign_id)
                ->onQueue(DraftConvertedLivePostsJob::QUEUE);

            if ($post->campaign) {
                $counters->syncCampaignFromPosts($post->campaign);
            }
        } catch (Throwable $exception) {
            Log::warning('RepublishConvertedSchedulePostAfterDomainReplaceJob failed', [
                'schedule_post_id' => $post->id,
                'error' => $exception->getMessage(),
            ]);

            $this->markFailed($post, $exception->getMessage(), $counters);
        }
    }

    private function markFailed(
        ScheduleCampaignPost $post,
        string $message,
        ScheduleCampaignTargetCounterService $counters,
    ): void {
        $post->forceFill([
            'conversion_phase' => 'failed',
            'status' => 'failed',
            'last_error' => $message,
            'last_conversion_error' => $message,
            'last_conversion_attempt_at' => now(),
        ])->save();

        if ($post->campaign) {
            $counters->syncCampaignFromPosts($post->campaign);
        }
    }
}
