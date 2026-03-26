<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignTask;
use App\Models\Admin\SidebarCampaignLink;
use App\Models\Admin\SidebarCampaignDomain;
use App\Services\BlogrollApiService;

class DeleteSidebarCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour for many remote deletes

    public function __construct(public int $sidebarCampaignId)
    {
        $this->onQueue('sidebar_deletions');
    }

    public function handle(): void
    {
        $campaign = SidebarCampaign::find($this->sidebarCampaignId);

        if (!$campaign) {
            Log::info('DeleteSidebarCampaignJob: campaign already gone', ['id' => $this->sidebarCampaignId]);
            return;
        }

        $tasks = SidebarCampaignTask::where('sidebar_campaign_id', $campaign->id)
            ->with('domainRow.domain')
            ->get();

        $deletedRemote = 0;
        $failedRemote = 0;

        foreach ($tasks as $index => $task) {
            if (!$task->remote_id) {
                continue;
            }
            $domain = $task->domainRow?->domain;
            if (!$domain || !$domain->api_key) {
                continue;
            }

            $res = BlogrollApiService::deleteEntryByRemoteId($domain->name, $domain->api_key, $task->remote_id);
            if ($res->successful()) {
                $deletedRemote++;
            } else {
                $failedRemote++;
                Log::warning('DeleteSidebarCampaignJob: remote blogroll delete failed', [
                    'task_id' => $task->id,
                    'index'   => $index,
                    'status'  => $res->status(),
                    'body'    => $res->body(),
                ]);
            }
        }

        DB::transaction(function () use ($campaign) {
            SidebarCampaignTask::where('sidebar_campaign_id', $campaign->id)->delete();
            SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)->delete();
            SidebarCampaignDomain::where('sidebar_campaign_id', $campaign->id)->delete();
            $campaign->delete();
        });

        Log::info('DeleteSidebarCampaignJob: completed', [
            'sidebar_campaign_id' => $this->sidebarCampaignId,
            'deleted_remote'      => $deletedRemote,
            'failed_remote'       => $failedRemote,
        ]);
    }
}
