<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\ScheduleCampaignRemotePostUpdateService;

class BulkUpdateScheduleCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /** @param array<int> $postIds */
    public function __construct(public array $postIds)
    {
        $this->onQueue('schedule_campaign_bulk_updates');
    }

    public function handle(): void
    {
        $updated = 0;
        $failed = 0;

        foreach ($this->postIds as $postId) {
            $post = ScheduleCampaignPost::with(['campaignArticle', 'campaignDomain.domain'])->find($postId);

            if (!$post || $post->status !== 'success' || empty($post->remote_id)) {
                continue;
            }

            $domain = $post->campaignDomain?->domain;
            if (!$domain || !$domain->api_key) {
                Log::warning('BulkUpdateScheduleCampaignPostsJob: missing domain or api_key', ['post_id' => $postId]);
                $failed++;
                continue;
            }

            try {
                [$title, $content] = ScheduleCampaignRemotePostUpdateService::fetchAndApplyKeywordUrl($post);
            } catch (\Throwable $e) {
                Log::warning('BulkUpdateScheduleCampaignPostsJob: fetch or apply failed', ['post_id' => $postId, 'message' => $e->getMessage()]);
                $failed++;
                continue;
            }

            $domainName = trim((string) $domain->name);
            if (!preg_match('~^https?://~i', $domainName)) {
                $domainName = 'https://' . $domainName;
            }
            $baseUrl = rtrim($domainName, '/');
            $url = $baseUrl . '/wp-json/external/v1/posts/update/' . $post->remote_id . '?api_key=' . urlencode((string) $domain->api_key);

            try {
                $response = Http::withoutVerifying()->timeout(120)->acceptJson()->asJson()->post($url, ['title' => $title, 'content' => $content]);
            } catch (\Throwable $e) {
                Log::warning('BulkUpdateScheduleCampaignPostsJob: remote update exception', ['post_id' => $postId, 'message' => $e->getMessage()]);
                $failed++;
                continue;
            }

            $json = $response->json();
            $ok = $response->successful() && (isset($json['success']) ? (bool) $json['success'] : true);

            if ($ok) {
                $updated++;
            } else {
                Log::warning('BulkUpdateScheduleCampaignPostsJob: remote update failed', ['post_id' => $postId, 'status' => $response->status()]);
                $failed++;
            }
        }

        Log::info('BulkUpdateScheduleCampaignPostsJob: completed', ['updated' => $updated, 'failed' => $failed, 'total' => count($this->postIds)]);
    }
}
