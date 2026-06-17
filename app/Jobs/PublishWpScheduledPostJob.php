<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Admin\Article;
use App\Models\Admin\WpScheduledCampaign;
use App\Models\Admin\WpScheduledCampaignArticle;
use App\Models\Admin\WpScheduledCampaignPost;
use App\Services\WpScheduledPostContentBuilder;

/**
 * Send a single WP Scheduled campaign post to WordPress.
 * For future scheduled_at: send date + status 'future' so WP schedules it.
 * For past scheduled_at: send date + status 'publish' (publish immediately; we keep scheduled_date for reporting).
 */
class PublishWpScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $postId)
    {
        $this->onQueue('wp_scheduled_campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec = config('campaign.jobs.lock_ttl_seconds');
        $maxAttempts = config('campaign.jobs.max_internal_retries');
        $baseBackoff = config('campaign.jobs.base_backoff_seconds');
        $lockToken = (string) Str::uuid();

        $post = DB::transaction(function () use ($lockToken, $lockTtlSec) {
            $p = WpScheduledCampaignPost::query()->with('campaign')->lockForUpdate()->find($this->postId);
            if (!$p || in_array($p->status, ['success', 'failed'], true)) return null;
            if ($p->campaign && in_array($p->campaign->status, ['paused', 'cancelled'], true)) return null;
            if ($p->next_retry_at && $p->next_retry_at->isFuture()) return null;
            if ($p->locked_at && $p->locked_at->gt(now()->subSeconds($lockTtlSec))) return null;

            $p->status = 'publishing';
            $p->locked_at = now();
            $p->lock_token = $lockToken;
            $p->save();

            WpScheduledCampaign::whereKey($p->wp_scheduled_campaign_id)->whereNull('started_at')->update(['started_at' => now()]);
            return $p;
        });

        if (!$post) return;

        $post->load(['campaign', 'campaignDomain.domain', 'campaignArticle.article']);

        try {
            [$title, $content] = WpScheduledPostContentBuilder::build($post);

            // ✅ UTF-8 Sanitization - clean malformed bytes before WordPress API posting
            $title = cleanUtf8($title, [
                'context' => 'wp_scheduled_api_post',
                'article_id' => $post->campaignArticle?->article_id,
                'post_id' => $post->id,
                'field' => 'title',
                'language' => $post->campaignArticle?->article?->language?->name,
            ]);

            $content = cleanUtf8($content, [
                'context' => 'wp_scheduled_api_post',
                'article_id' => $post->campaignArticle?->article_id,
                'post_id' => $post->id,
                'field' => 'content',
                'language' => $post->campaignArticle?->article?->language?->name,
            ]);

            $remote = $this->postToWordPress($post, $title, $content);

            DB::transaction(function () use ($post, $remote, $title) {
                $fresh = WpScheduledCampaignPost::lockForUpdate()->find($post->id);
                if (!$fresh || $fresh->lock_token !== $post->lock_token) return;

                $json = $remote['json'];
                $fresh->status = in_array($json['status'] ?? null, ['publish', 'future'], true) ? 'success' : 'failed';
                $fresh->remote_id = $json['post_id'] ?? null;
                $fresh->remote_status = $json['status'] ?? null;
                $fresh->remote_scheduled_at = $post->scheduled_at;
                $fresh->http_status = $remote['http_status'];
                $fresh->remote_title = $title;
                // Use slug URL from API: remote_url (as in Postman) may already be slug; else link/permalink/url; if only ?p=ID then resolve permalink
                $url = $json['remote_url'] ?? $json['link'] ?? $json['permalink'] ?? $json['url'] ?? null;
                if ($url && str_contains($url, '?p=') && !empty($json['post_id'])) {
                    $permalink = $this->resolvePermalink($post->campaignDomain->domain->name ?? '', $json['post_id'], $post->campaignDomain->domain->api_key ?? '');
                    if ($permalink) {
                        $url = $permalink;
                    }
                }
                $fresh->remote_url = $url;
                $fresh->remote_response = $json;
                $fresh->published_at = ($fresh->remote_status === 'publish') ? now() : null;
                $fresh->last_error = null;
                $fresh->next_retry_at = null;
                $fresh->locked_at = null;
                $fresh->lock_token = null;
                $fresh->save();

                if ($fresh->status === 'success') {
                    WpScheduledCampaign::whereKey($fresh->wp_scheduled_campaign_id)->increment('completed_targets');
                    $ca = WpScheduledCampaignArticle::lockForUpdate()->find($fresh->wp_scheduled_campaign_article_id);
                    if ($ca && $ca->article_id) {
                        $art = Article::find($ca->article_id);
                        if ($art) {
                            $ca->update([
                                'article_title_snapshot' => $art->name,
                                'article_body_snapshot'  => $art->description,
                            ]);
                            $art->delete();
                        }
                    }
                } else {
                    WpScheduledCampaign::whereKey($fresh->wp_scheduled_campaign_id)->increment('failed_targets');
                }
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($post, $e, $maxAttempts, $baseBackoff) {
                $fresh = WpScheduledCampaignPost::lockForUpdate()->find($post->id);
                if (!$fresh || $fresh->lock_token !== $post->lock_token) return;
                $fresh->attempt_count++;
                $fresh->last_error = $e->getMessage();
                if ($fresh->attempt_count < $maxAttempts) {
                    $delay = min((int) ($baseBackoff * (2 ** ($fresh->attempt_count - 1))), 3600);
                    $fresh->status = 'queued';
                    $fresh->next_retry_at = now()->addSeconds($delay);
                    $fresh->locked_at = null;
                    $fresh->lock_token = null;
                    $fresh->save();
                    self::dispatch($fresh->id)->onQueue('wp_scheduled_campaigns')->delay(now()->addSeconds($delay));
                } else {
                    $fresh->status = 'failed';
                    $fresh->next_retry_at = null;
                    $fresh->locked_at = null;
                    $fresh->lock_token = null;
                    $fresh->save();
                    WpScheduledCampaign::whereKey($fresh->wp_scheduled_campaign_id)->increment('failed_targets');
                }
            });
        } finally {
            $this->finalizeCampaignIfDone($post->wp_scheduled_campaign_id);
        }
    }

    /**
     * Send to WordPress with date: future → status 'future' (WP schedules); past → status 'publish'.
     */
    private function postToWordPress(WpScheduledCampaignPost $post, string $title, string $content): array
    {
        $domain = trim((string) $post->campaignDomain->domain->name);
        if (!preg_match('~^https?://~i', $domain)) $domain = 'https://' . $domain;
        $endpoint = rtrim($domain, '/') . '/wp-json/external/v1/posts/create';

        // Use WordPress scheduler style payload (matches Postman example):
        // status: "publish" + schedule_time: "Y-m-d H:i:s"
        $scheduledAt = $post->scheduled_at ? Carbon::parse($post->scheduled_at) : now();
        $scheduleTimeStr = $scheduledAt->format('Y-m-d H:i:s');

        $payload = [
            'title'         => $title,
            'content'       => $content,
            'status'        => 'publish',
            'schedule_time' => $scheduleTimeStr,
            'post_type'     => 'post',
            'api_key'       => (string) $post->campaignDomain->domain->api_key,
        ];

        // ✅ UTF-8 Safe: Use proper headers and ensure payload is clean
        $res = Http::withoutVerifying()
            ->timeout(180)
            ->acceptJson()
            ->contentType('application/json; charset=utf-8')
            ->withBody(safeJsonEncode($payload), 'application/json; charset=utf-8')
            ->post($endpoint);
        if (!$res->successful()) throw new \Exception("WP API failed ({$res->status()}): " . $res->body());
        $json = $res->json();
        if (!is_array($json)) throw new \Exception("WP API non-JSON: " . $res->body());
        return ['json' => $json, 'http_status' => $res->status()];
    }

    /**
     * Try to get slug/permalink for a post from WordPress REST API (so report can show pretty URL).
     */
    private function resolvePermalink(string $domain, $postId, string $apiKey): ?string
    {
        $domain = trim($domain);
        if (!preg_match('~^https?://~i', $domain)) {
            $domain = 'https://' . $domain;
        }
        $base = rtrim($domain, '/');
        $url = $base . '/wp-json/wp/v2/posts/' . (int) $postId;
        try {
            $res = Http::withoutVerifying()->timeout(10)->acceptJson()->get($url, ['_embed' => '0']);
            if (!$res->successful()) {
                return null;
            }
            $data = $res->json();
            return is_array($data) ? ($data['link'] ?? null) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function finalizeCampaignIfDone(int $campaignId): void
    {
        $campaign = WpScheduledCampaign::find($campaignId);
        if (!$campaign) return;
        if ($campaign->completed_targets + $campaign->failed_targets < $campaign->total_targets) return;
        $campaign->finished_at = now();
        if ($campaign->failed_targets === 0) $campaign->status = 'completed';
        elseif ($campaign->completed_targets > 0) $campaign->status = 'semi_failed';
        else $campaign->status = 'failed';
        $campaign->save();
    }
}
