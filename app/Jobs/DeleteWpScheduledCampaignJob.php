<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\WpScheduledCampaign;
use App\Models\Admin\WpScheduledCampaignPost;
use App\Models\Admin\WpScheduledCampaignArticle;
use App\Models\Admin\WpScheduledCampaignDomain;
use App\Models\Admin\WpScheduledCampaignDate;
use App\Models\Admin\Article;

/**
 * Delete a WP Scheduled campaign:
 * - Remove remote WordPress posts for successful entries (publish/future)
 * - Unlock queued articles that were never sent
 * - Cleanup local posts/articles/domains/dates + campaign row
 */
class DeleteWpScheduledCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // up to 1 hour for many remote deletes

    public function __construct(public int $campaignId)
    {
        $this->onQueue('wp_scheduled_campaign_deletions');
    }

    public function handle(): void
    {
        $campaign = WpScheduledCampaign::find($this->campaignId);

        if (!$campaign) {
            Log::info('DeleteWpScheduledCampaignJob: campaign already gone', ['id' => $this->campaignId]);
            return;
        }

        $posts = WpScheduledCampaignPost::where('wp_scheduled_campaign_id', $campaign->id)
            ->with(['campaignDomain.domain', 'campaignArticle'])
            ->get();

        $deletedRemote = 0;
        $failedRemote  = 0;

        foreach ($posts as $post) {
            // Unlock article for queued posts (never sent)
            if ($post->status === 'queued' && $post->campaignArticle) {
                $article = Article::find($post->campaignArticle->article_id);
                if ($article) {
                    $article->update([
                        'lock_at' => null,
                        'status'  => 0,
                    ]);
                }
            }

            // Remote delete only when a WordPress post exists (success with remote_id)
            if ($post->status !== 'success' || !$post->remote_id) {
                continue;
            }

            $domain = $post->campaignDomain?->domain;
            if (!$domain || !$domain->api_key) {
                continue;
            }

            $domainName = trim((string) $domain->name);
            if (!preg_match('~^https?://~i', $domainName)) {
                $domainName = 'https://' . $domainName;
            }

            $url = rtrim($domainName, '/') . '/wp-json/external/v1/posts/delete/' . $post->remote_id . '?api_key=' . urlencode($domain->api_key);

            try {
                $response = Http::withoutVerifying()->timeout(60)->asJson()->delete($url);
                if ($response->successful()) {
                    $deletedRemote++;
                } else {
                    $failedRemote++;
                    Log::warning('DeleteWpScheduledCampaignJob: remote delete failed', [
                        'post_id'   => $post->id,
                        'remote_id' => $post->remote_id,
                        'status'    => $response->status(),
                        'body'      => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failedRemote++;
                Log::warning('DeleteWpScheduledCampaignJob: remote delete exception', [
                    'post_id' => $post->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($campaign) {
            WpScheduledCampaignPost::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            WpScheduledCampaignDomain::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            WpScheduledCampaignDate::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('DeleteWpScheduledCampaignJob: completed', [
            'campaign_id'    => $this->campaignId,
            'deleted_remote' => $deletedRemote,
            'failed_remote'  => $failedRemote,
        ]);
    }
}

