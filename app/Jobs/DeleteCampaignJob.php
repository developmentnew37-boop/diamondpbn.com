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
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\CampaignDomain;

class DeleteCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour for many remote deletes

    public function __construct(public int $campaignId)
    {
        $this->onQueue('deletions');
    }

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign) {
            Log::info('DeleteCampaignJob: campaign already gone', ['id' => $this->campaignId]);
            return;
        }

        $posts = CampaignPost::where('campaign_id', $campaign->id)
            ->with('campaignDomain.domain')
            ->get();

        $deletedRemote = 0;
        $failedRemote = 0;

        foreach ($posts as $post) {
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
                    Log::warning('DeleteCampaignJob: remote delete failed', [
                        'post_id'   => $post->id,
                        'remote_id' => $post->remote_id,
                        'status'   => $response->status(),
                        'body'     => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failedRemote++;
                Log::warning('DeleteCampaignJob: remote delete exception', [
                    'post_id' => $post->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($campaign) {
            CampaignPost::where('campaign_id', $campaign->id)->delete();
            CampaignArticle::where('campaign_id', $campaign->id)->delete();
            CampaignDomain::where('campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('DeleteCampaignJob: completed', [
            'campaign_id'     => $this->campaignId,
            'deleted_remote'  => $deletedRemote,
            'failed_remote'   => $failedRemote,
        ]);
    }
}
