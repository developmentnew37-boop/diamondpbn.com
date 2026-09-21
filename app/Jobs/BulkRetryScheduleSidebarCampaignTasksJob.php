<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkRetryScheduleSidebarCampaignTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param  int[]  $campaignIds
     */
    public function __construct(
        public array $campaignIds,
        public bool $includeStuck = false,
    ) {
        $this->onQueue('bulk_retry_scheduled_sidebar_campaigns');
    }

    public static function retryableQuery(int $campaignId, bool $includeStuck = false): Builder
    {
        $query = ScheduleSidebarCampaignTask::query()
            ->where('schedule_sidebar_campaign_id', $campaignId)
            ->where(function ($q) {
                $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
            });

        if ($includeStuck) {
            return $query->whereIn('status', ['queued', 'failed', 'publishing']);
        }

        return $query->where('status', 'failed');
    }

    public function handle(ScheduleSidebarCampaignTargetCounterService $counters): void
    {
        $totalRetried = 0;
        $skipped = 0;

        foreach ($this->campaignIds as $campaignId) {
            $campaign = ScheduleSidebarCampaign::find($campaignId);

            if (! $campaign) {
                Log::warning('BulkRetryScheduleSidebarCampaignTasksJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetryScheduleSidebarCampaignTasksJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            $failedTasks = self::retryableQuery((int) $campaignId, $this->includeStuck)->get();
            if ($failedTasks->isEmpty()) {
                continue;
            }

            $failedCount = 0;

            foreach ($failedTasks as $task) {
                $generation = (int) ($task->dispatch_generation ?? 0);
                if ($this->includeStuck) {
                    $generation++;
                }

                $wasFailed = $task->status === 'failed';
                $task->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'dispatch_generation' => $generation,
                ]);

                PublishScheduledSidebarBlogrollJob::dispatch($task->id, $generation)->onQueue('scheduled_sidebar_campaigns');
                $totalRetried++;
                if ($wasFailed) {
                    $failedCount++;
                }
            }

            if ($failedCount > 0) {
                $counters->accountForFailedTaskRetries($campaign, $failedCount);
            }
            $counters->syncCampaignFromTasks($campaign->fresh());
        }

        Log::info('BulkRetryScheduleSidebarCampaignTasksJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'tasks_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
        ]);
    }
}
