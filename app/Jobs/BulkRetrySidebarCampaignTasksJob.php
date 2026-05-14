<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignTask;

class BulkRetrySidebarCampaignTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param int[] $campaignIds
     */
    public function __construct(
        public array $campaignIds,
    ) {
        $this->onQueue('bulk_retry_sidebar_campaigns');
    }

    public function handle(): void
    {
        $totalRetried = 0;
        $skipped = 0;

        foreach ($this->campaignIds as $campaignId) {
            $campaign = SidebarCampaign::find($campaignId);

            if (!$campaign) {
                Log::warning('BulkRetrySidebarCampaignTasksJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;
                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetrySidebarCampaignTasksJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;
                continue;
            }

            $failedTasks = SidebarCampaignTask::where('sidebar_campaign_id', $campaignId)
                ->where('status', 'failed')
                ->get();

            foreach ($failedTasks as $task) {
                $task->update([
                    'status'        => 'queued',
                    'attempt_count' => 0,
                    'next_retry_at' => null,
                    'locked_at'     => null,
                    'lock_token'    => null,
                    'last_error'    => null,
                ]);

                PublishSidebarBlogrollJob::dispatch($task->id)->onQueue('sidebar_campaigns');
                $totalRetried++;
            }
        }

        Log::info('BulkRetrySidebarCampaignTasksJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'tasks_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
        ]);
    }
}
