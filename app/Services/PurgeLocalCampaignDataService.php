<?php

namespace App\Services;

use App\Models\Admin\Article;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleCampaignDate;
use App\Models\Admin\ScheduleCampaignDomain;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignDate;
use App\Models\Admin\ScheduleSidebarCampaignDomain;
use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignDomain;
use App\Models\Admin\SidebarCampaignLink;
use App\Models\Admin\SidebarCampaignTask;
use App\Models\Admin\WpScheduledCampaign;
use App\Models\Admin\WpScheduledCampaignArticle;
use App\Models\Admin\WpScheduledCampaignDate;
use App\Models\Admin\WpScheduledCampaignDomain;
use App\Models\Admin\WpScheduledCampaignPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes campaign rows from the application database only — no HTTP calls to remote sites.
 * Published posts/links remain on WordPress / blogroll / hidden-links APIs.
 */
final class PurgeLocalCampaignDataService
{
    public static function purgePbnCampaign(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                return;
            }
            CampaignPost::where('campaign_id', $campaign->id)->delete();
            CampaignArticle::where('campaign_id', $campaign->id)->delete();
            CampaignDomain::where('campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('PurgeLocalCampaignDataService: PBN campaign purged (local only)', ['campaign_id' => $campaignId]);
    }

    public static function purgeScheduleCampaign(int $campaignId): void
    {
        $campaign = ScheduleCampaign::find($campaignId);
        if (!$campaign) {
            return;
        }

        $posts = ScheduleCampaignPost::where('schedule_campaign_id', $campaign->id)
            ->with('campaignArticle')
            ->get();

        foreach ($posts as $post) {
            if ($post->status === 'queued' && $post->campaignArticle) {
                $article = Article::find($post->campaignArticle->article_id);
                if ($article) {
                    $article->update(['lock_at' => null]);
                }
            }
        }

        DB::transaction(function () use ($campaign) {
            ScheduleCampaignPost::where('schedule_campaign_id', $campaign->id)->delete();
            ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)->delete();
            ScheduleCampaignDomain::where('schedule_campaign_id', $campaign->id)->delete();
            ScheduleCampaignDate::where('schedule_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('PurgeLocalCampaignDataService: schedule campaign purged (local only)', ['campaign_id' => $campaignId]);
    }

    public static function purgeWpScheduledCampaign(int $campaignId): void
    {
        $campaign = WpScheduledCampaign::find($campaignId);
        if (!$campaign) {
            return;
        }

        $posts = WpScheduledCampaignPost::where('wp_scheduled_campaign_id', $campaign->id)
            ->with('campaignArticle')
            ->get();

        foreach ($posts as $post) {
            if ($post->status === 'queued' && $post->campaignArticle) {
                $article = Article::find($post->campaignArticle->article_id);
                if ($article) {
                    $article->update([
                        'lock_at' => null,
                        'status'  => 0,
                    ]);
                }
            }
        }

        DB::transaction(function () use ($campaign) {
            WpScheduledCampaignPost::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            WpScheduledCampaignDomain::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            WpScheduledCampaignDate::where('wp_scheduled_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('PurgeLocalCampaignDataService: WP scheduled campaign purged (local only)', ['campaign_id' => $campaignId]);
    }

    public static function purgeSidebarCampaign(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = SidebarCampaign::find($campaignId);
            if (!$campaign) {
                return;
            }
            SidebarCampaignTask::where('sidebar_campaign_id', $campaign->id)->delete();
            SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)->delete();
            SidebarCampaignDomain::where('sidebar_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('PurgeLocalCampaignDataService: sidebar campaign purged (local only)', ['campaign_id' => $campaignId]);
    }

    public static function purgeScheduleSidebarCampaign(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = ScheduleSidebarCampaign::find($campaignId);
            if (!$campaign) {
                return;
            }
            ScheduleSidebarCampaignTask::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            ScheduleSidebarCampaignDomain::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            ScheduleSidebarCampaignDate::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('PurgeLocalCampaignDataService: schedule sidebar campaign purged (local only)', ['campaign_id' => $campaignId]);
    }

    public static function purgeHiddenLinksCampaign(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = HiddenLinksCampaign::find($campaignId);
            if (!$campaign) {
                return;
            }
            HiddenLinksCampaignTasks::where('hidden_links_campaigns_id', $campaign->id)->delete();
            HiddenLinksCampaignLinks::where('hidden_links_campaigns_id', $campaign->id)->delete();
            HiddenLinksCampaignDomains::where('hidden_links_campaigns_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('PurgeLocalCampaignDataService: hidden links campaign purged (local only)', ['campaign_id' => $campaignId]);
    }
}
