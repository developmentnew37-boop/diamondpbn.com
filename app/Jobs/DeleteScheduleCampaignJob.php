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
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleCampaignDomain;
use App\Models\Admin\ScheduleCampaignDate;
use App\Models\Admin\Article;

/**
 * Delete a Schedule Campaign: remove remote WordPress posts for successful entries,
 * unlock queued articles, then delete local posts, articles, domains, date rows, and campaign.
 */
class DeleteScheduleCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $campaignId)
    {
        $this->onQueue('schedule_campaign_deletions');
    }

    public function handle(): void
    {
        $campaign = ScheduleCampaign::find($this->campaignId);

        if (!$campaign) {
            Log::info('DeleteScheduleCampaignJob: campaign already gone', ['id' => $this->campaignId]);
            return;
        }

        $posts = ScheduleCampaignPost::where('schedule_campaign_id', $campaign->id)
            ->with(['campaignDomain.domain', 'campaignArticle'])
            ->get();

        $deletedRemote = 0;
        $failedRemote  = 0;

        foreach ($posts as $post) {
            if ($post->status === 'queued' && $post->campaignArticle) {
                $article = Article::find($post->campaignArticle->article_id);
                if ($article) {
                    $article->update(['lock_at' => null]);
                }
            }

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

            $url = rtrim($domainName, '/') . '/wp-json/external/v1/posts/delete/' . $post->remote_id
                . '?api_key=' . urlencode($domain->api_key);

            try {
                $response = Http::withoutVerifying()->timeout(60)->asJson()->delete($url);
                if ($response->successful()) {
                    $deletedRemote++;
                } else {
                    $failedRemote++;
                    Log::warning('DeleteScheduleCampaignJob: remote delete failed', [
                        'post_id'   => $post->id,
                        'remote_id' => $post->remote_id,
                        'status'    => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failedRemote++;
                Log::warning('DeleteScheduleCampaignJob: remote delete exception', [
                    'post_id' => $post->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($campaign) {
            ScheduleCampaignPost::where('schedule_campaign_id', $campaign->id)->delete();
            ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)->delete();
            ScheduleCampaignDomain::where('schedule_campaign_id', $campaign->id)->delete();
            if (class_exists(ScheduleCampaignDate::class)) {
                ScheduleCampaignDate::where('schedule_campaign_id', $campaign->id)->delete();
            }
            $campaign->delete();
        });

        Log::info('DeleteScheduleCampaignJob: completed', [
            'campaign_id'    => $this->campaignId,
            'deleted_remote' => $deletedRemote,
            'failed_remote'  => $failedRemote,
        ]);
    }
}
