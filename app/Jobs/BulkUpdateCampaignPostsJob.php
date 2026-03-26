<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\CampaignPost;
use App\Services\RemotePostUpdateService;

class BulkUpdateCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> */
    public array $replacePairs = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $removePairs = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $addPairs = [];

    /**
     * @param int[] $campaignPostIds
     * @param array<int, array{0: string, 1: string, 2: string, 3: string}> $replacePairs [old_keyword, old_url, new_keyword, new_url]
     * @param array<int, array{0: string, 1: string}> $removePairs [keyword, url]
     * @param array<int, array{0: string, 1: string}> $addPairs [keyword, url]
     */
    public function __construct(
        public array $campaignPostIds,
        array $replacePairs = [],
        array $removePairs = [],
        array $addPairs = [],
    ) {
        $this->onQueue('bulk_updates');
        $this->replacePairs = $replacePairs;
        $this->removePairs  = $removePairs;
        $this->addPairs     = $addPairs;
    }

    public function handle(): void
    {
        $updated = 0;
        $failed  = 0;

        foreach ($this->campaignPostIds as $postId) {
            $post = CampaignPost::with('campaignDomain.domain', 'campaignArticle')->find($postId);
            if (!$post || $post->status !== 'success' || !$post->remote_id) {
                continue;
            }

            $domain = $post->campaignDomain?->domain;
            if (!$domain || !$domain->api_key) {
                continue;
            }

            $baseUrl = trim((string) $domain->name);
            if (!preg_match('~^https?://~i', $baseUrl)) {
                $baseUrl = 'https://' . $baseUrl;
            }
            $baseUrl = rtrim($baseUrl, '/');

            $fetched = RemotePostUpdateService::fetchPost($baseUrl, $domain->api_key, (string) $post->remote_id);
            if (!$fetched || ($fetched['post_title'] === '' && $fetched['post_content'] === '')) {
                $post->update(['content_updated_at' => null]); // clear optimistic flag when fetch fails
                Log::warning('BulkUpdateCampaignPostsJob: fetch failed or empty', ['post_id' => $postId]);
                $failed++;
                continue;
            }

            $content = RemotePostUpdateService::applyChangesToHtml(
                $fetched['post_content'],
                $this->replacePairs,
                $this->removePairs,
                $this->addPairs
            );

            $updateUrl = $baseUrl . '/wp-json/external/v1/posts/update/' . $post->remote_id;
            $response  = Http::withoutVerifying()
                ->timeout(120)
                ->asJson()
                ->post($updateUrl, [
                    'title'   => $fetched['post_title'],
                    'content' => $content,
                    'api_key' => $domain->api_key,
                ]);

            if ($response->successful()) {
                $post->update(['content_updated_at' => now()]);
                $updated++;
            } else {
                $post->update(['content_updated_at' => null]); // clear so UI does not show "Updated" for failed
                $failed++;
                Log::warning('BulkUpdateCampaignPostsJob: update failed', [
                    'post_id' => $postId,
                    'body'    => $response->body(),
                ]);
            }
        }

        Log::info('BulkUpdateCampaignPostsJob: completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->campaignPostIds),
        ]);
    }
}
