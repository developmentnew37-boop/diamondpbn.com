<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\WpScheduledCampaignPost;
use App\Models\Admin\WpScheduledCampaign;

/**
 * Sync a single WP scheduled post status from WordPress.
 * Tries external API first, then WP REST API. Updates remote_status and published_at for accurate Live/Missed/Scheduled display.
 */
class SyncWpScheduledPostStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public int $postId)
    {
        $this->onQueue('wp_scheduled_sync');
    }

    public function handle(): void
    {
        $post = WpScheduledCampaignPost::with(['campaignDomain.domain', 'campaign'])->find($this->postId);
        if (!$post || !$post->remote_id) {
            return;
        }

        $domain = $post->campaignDomain?->domain;
        if (!$domain) {
            return;
        }

        $base = rtrim(preg_match('~^https?://~i', $domain->name) ? $domain->name : 'https://' . $domain->name, '/');
        $status = null;
        $link = null;

        if ($domain->api_key) {
            $url = $base . '/wp-json/external/v1/posts/status/' . $post->remote_id . '?api_key=' . urlencode($domain->api_key);
            try {
                $res = Http::withoutVerifying()->timeout(15)->acceptJson()->get($url);
                if ($res->successful()) {
                    $json = $res->json();
                    if (is_array($json)) {
                        $status = $json['status'] ?? null;
                        $link = $json['remote_url'] ?? $json['link'] ?? $json['url'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('SyncWpScheduledPostStatus: external API ' . $e->getMessage(), ['post_id' => $this->postId]);
            }
        }

        $needSlug = !$link || (is_string($link) && str_contains($link, '?p='));
        if ($status === null || $needSlug) {
            try {
                $res = Http::withoutVerifying()->timeout(10)->acceptJson()->get($base . '/wp-json/wp/v2/posts/' . $post->remote_id);
                if ($res->successful()) {
                    $data = $res->json();
                    if (is_array($data)) {
                        if ($status === null) {
                            $status = $data['status'] ?? null;
                        }
                        $restLink = $data['link'] ?? null;
                        if ($restLink && is_string($restLink) && !str_contains($restLink, '?p=')) {
                            $link = $restLink;
                        } elseif ($needSlug && $restLink) {
                            $link = $restLink;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('SyncWpScheduledPostStatus: WP REST ' . $e->getMessage(), ['post_id' => $this->postId]);
            }
        }

        if ($status === null) {
            return;
        }

        $updates = ['remote_status' => $status];
        if ($status === 'publish') {
            $updates['published_at'] = $post->published_at ?? now();
            if ($post->status !== 'success') {
                $updates['status'] = 'success';
                WpScheduledCampaign::whereKey($post->wp_scheduled_campaign_id)->increment('completed_targets');
            }
        }
        $currentUrl = $post->remote_url ?? '';
        if ($link && is_string($link)) {
            $isSlug = !str_contains($link, '?p=');
            if ($isSlug || $currentUrl === '' || str_contains($currentUrl, '?p=')) {
                $updates['remote_url'] = $link;
            }
        }
        $post->update($updates);
    }
}
