<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Services\HiddenLinksApiService;

/**
 * Bulk delete hidden link campaigns: for each campaign, remove links from remote (where remote_id),
 * then delete all tasks, links, domains and the campaign.
 */
class BulkDeleteHiddenLinkCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @param int[] $campaignIds */
    public function __construct(public array $campaignIds)
    {
        $this->onQueue('delete_hidden_links_campaign');
    }

    public function handle(): void
    {
        $campaignIds = array_values(array_filter(array_map('intval', $this->campaignIds)));
        if (empty($campaignIds)) {
            return;
        }

        $campaigns = HiddenLinksCampaign::whereIn('id', $campaignIds)->get();
        $deletedRemote = 0;

        foreach ($campaigns as $campaign) {
            $tasks = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow'])
                ->where('hidden_links_campaigns_id', $campaign->id)
                ->get();

            foreach ($tasks as $task) {
                if ($task->remote_id) {
                    $domain = $task->domainRow?->domain;
                    if ($domain && $domain->api_key) {
                        $res = HiddenLinksApiService::deleteEntry($domain->name, $domain->api_key, $task->remote_id);
                        if ($res->successful()) {
                            $deletedRemote++;
                        }
                    }
                }

                $linkId      = $task->hidden_links_campaigns_link_id;
                $domainRowId = $task->hidden_links_campaigns_domain_id;
                $task->delete();
                if ($linkId) {
                    HiddenLinksCampaignLinks::where('id', $linkId)->delete();
                }
                if ($domainRowId) {
                    HiddenLinksCampaignDomains::where('id', $domainRowId)->delete();
                }
            }

            HiddenLinksCampaignLinks::where('hidden_links_campaigns_id', $campaign->id)->delete();
            HiddenLinksCampaignDomains::where('hidden_links_campaigns_id', $campaign->id)->delete();
            $campaign->delete();
        }

        Log::info('BulkDeleteHiddenLinkCampaignsJob: completed', [
            'campaign_ids'     => $campaignIds,
            'deleted_remote'   => $deletedRemote,
            'campaigns_count'  => $campaigns->count(),
        ]);
    }
}
