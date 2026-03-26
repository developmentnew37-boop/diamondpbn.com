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
use App\Services\WpScheduledRemotePostUpdateService;

/**
 * Updates already-published WP Scheduled posts on the remote WordPress site: fetches current
 * title/content from the remote site, applies new keyword/URL from the database, then
 * updates the post on the remote. Used after bulk keyword/URL edit.
 * Queue: wp_scheduled_campaign_bulk_updates.
 */
class BulkUpdateWpScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 min for many posts

    /** @param array<int> $postIds WpScheduledCampaignPost ids */
    public function __construct(public array $postIds)
    {
        $this->onQueue('wp_scheduled_campaign_bulk_updates');
    }

    public function handle(): void
    {
        $updated = 0;
        $failed = 0;

        foreach ($this->postIds as $postId) {
            $post = WpScheduledCampaignPost::with(['campaignArticle', 'campaignDomain.domain'])
                ->find($postId);

            if (!$post || $post->status !== 'success' || empty($post->remote_id)) {
                continue;
            }

            $domain = $post->campaignDomain?->domain;
            if (!$domain || !$domain->api_key) {
                Log::warning('BulkUpdateWpScheduledPostsJob: missing domain or api_key', ['post_id' => $postId]);
                $failed++;
                continue;
            }

            try {
                [$title, $content] = WpScheduledRemotePostUpdateService::fetchAndApplyKeywordUrl($post);
            } catch (\Throwable $e) {
                Log::warning('BulkUpdateWpScheduledPostsJob: fetch or apply failed', [
                    'post_id' => $postId,
                    'message' => $e->getMessage(),
                ]);
                $failed++;
                continue;
            }

            $domainName = trim((string) $domain->name);
            if (!preg_match('~^https?://~i', $domainName)) {
                $domainName = 'https://' . $domainName;
            }
            $baseUrl = rtrim($domainName, '/');
            $url = $baseUrl . '/wp-json/external/v1/posts/update/' . $post->remote_id
                . '?api_key=' . urlencode((string) $domain->api_key);

            try {
                $response = Http::withoutVerifying()
                    ->timeout(120)
                    ->acceptJson()
                    ->asJson()
                    ->post($url, [
                        'title'   => $title,
                        'content' => $content,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('BulkUpdateWpScheduledPostsJob: remote update exception', [
                    'post_id'   => $postId,
                    'remote_id' => $post->remote_id,
                    'message'   => $e->getMessage(),
                ]);
                $failed++;
                continue;
            }

            $json = $response->json();
            $ok = $response->successful() && (isset($json['success']) ? (bool) $json['success'] : true);

            if ($ok) {
                if (is_array($json)) {
                    $post->update(['remote_response' => array_merge(is_array($post->remote_response) ? $post->remote_response : [], $json)]);
                }
                $updated++;
            } else {
                Log::warning('BulkUpdateWpScheduledPostsJob: remote update failed', [
                    'post_id'   => $postId,
                    'remote_id' => $post->remote_id,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);
                $failed++;
            }
        }

        Log::info('BulkUpdateWpScheduledPostsJob: completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->postIds),
        ]);
    }
}
