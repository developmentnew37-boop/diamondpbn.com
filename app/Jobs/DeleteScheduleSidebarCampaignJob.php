<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignDate;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\ScheduleSidebarCampaignDomain;
use App\Services\BlogrollApiService;

/**
 * Delete a Schedule Sidebar Campaign: remove blogroll entries on remote, then delete tasks, links, domains, campaign.
 */
class DeleteScheduleSidebarCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $campaignId)
    {
        $this->onQueue('schedule_sidebar_deletions');
    }

    public function handle(): void
    {
        $campaign = ScheduleSidebarCampaign::find($this->campaignId);

        if (!$campaign) {
            Log::info('DeleteScheduleSidebarCampaignJob: campaign already gone', ['id' => $this->campaignId]);
            return;
        }

        $tasks = ScheduleSidebarCampaignTask::where('schedule_sidebar_campaign_id', $campaign->id)
            ->with('domain.domain')
            ->get();

        $deletedRemote = 0;
        $failedRemote  = 0;

        foreach ($tasks as $task) {
            if (!$task->remote_id) {
                continue;
            }
            $domain = $task->domain?->domain;
            if (!$domain || !$domain->api_key) {
                continue;
            }

            $res = BlogrollApiService::deleteEntryByRemoteId($domain->name, $domain->api_key, (string) $task->remote_id);
            if ($res->successful()) {
                $deletedRemote++;
            } else {
                $failedRemote++;
                Log::warning('DeleteScheduleSidebarCampaignJob: remote delete failed', [
                    'task_id' => $task->id,
                    'status'  => $res->status(),
                    'body'    => $res->body(),
                ]);
            }
        }

        DB::transaction(function () use ($campaign) {
            ScheduleSidebarCampaignTask::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            ScheduleSidebarCampaignDomain::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            ScheduleSidebarCampaignDate::where('schedule_sidebar_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('DeleteScheduleSidebarCampaignJob: completed', [
            'campaign_id'    => $this->campaignId,
            'deleted_remote' => $deletedRemote,
            'failed_remote'  => $failedRemote,
        ]);
    }
}
