<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Services\HiddenLinksApiService;

/**
 * Bulk delete hidden link tasks: remote delete then DB cleanup and campaign count decrements.
 */
class BulkDeleteHiddenLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @param int[] $taskIds */
    public function __construct(public int $campaignId, public array $taskIds)
    {
        $this->onQueue('delete_hidden_links');
    }

    public function handle(): void
    {
        $campaign = HiddenLinksCampaign::find($this->campaignId);
        if (!$campaign) {
            Log::info('BulkDeleteHiddenLinksJob: campaign not found', ['id' => $this->campaignId]);
            return;
        }

        $tasks = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow'])
            ->where('hidden_links_campaigns_id', $campaign->id)
            ->whereIn('id', $this->taskIds)
            ->get();

        $deletedRemote = 0;
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
        }

        DB::transaction(function () use ($tasks) {
            $campaign = HiddenLinksCampaign::lockForUpdate()->find($this->campaignId);
            if (!$campaign) {
                return;
            }

            foreach ($tasks as $task) {
                $postStatus = $task->status;

                if ($campaign->total_targets > 0) {
                    $campaign->decrement('total_targets');
                }
                if ($postStatus === 'success' && $campaign->completed_targets > 0) {
                    $campaign->decrement('completed_targets');
                } elseif ($postStatus === 'failed' && $campaign->failed_targets > 0) {
                    $campaign->decrement('failed_targets');
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

            $campaign->refresh();
            if ($campaign->total_targets <= 0) {
                $campaign->delete();
            }
        });

        Log::info('BulkDeleteHiddenLinksJob: completed', [
            'campaign_id'     => $this->campaignId,
            'deleted_remote'  => $deletedRemote,
            'tasks_processed' => $tasks->count(),
        ]);
    }
}
