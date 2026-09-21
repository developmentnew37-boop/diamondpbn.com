<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Immediately dispatch remaining (non-success) schedule sidebar tasks for publish.
 * Does NOT change schedule_at — original dates stay on details/report.
 */
class PublishRemainingScheduleSidebarCampaignTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public int $campaignId,
    ) {
        $this->onQueue('bulk_retry_scheduled_sidebar_campaigns');
    }

    public function handle(ScheduleSidebarCampaignTargetCounterService $counters): void
    {
        $summary = self::publishRemaining($this->campaignId, $counters);

        Log::info('PublishRemainingScheduleSidebarCampaignTasksJob completed', [
            'campaign_id' => $this->campaignId,
            ...$summary,
        ]);
    }

    /**
     * @return array{queued: int, skipped: bool, reason: ?string}
     */
    public static function publishRemaining(int $campaignId, ScheduleSidebarCampaignTargetCounterService $counters): array
    {
        $campaign = ScheduleSidebarCampaign::find($campaignId);

        if (! $campaign) {
            return ['queued' => 0, 'skipped' => true, 'reason' => 'not_found'];
        }

        if (in_array((string) $campaign->status, ['paused', 'cancelled'], true)) {
            return ['queued' => 0, 'skipped' => true, 'reason' => 'paused_or_cancelled'];
        }

        $tasks = ScheduleSidebarCampaignTask::query()
            ->where('schedule_sidebar_campaign_id', $campaignId)
            ->whereIn('status', ['queued', 'failed', 'publishing'])
            ->where(function ($q) {
                $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
            })
            ->get();

        if ($tasks->isEmpty()) {
            return ['queued' => 0, 'skipped' => true, 'reason' => 'none_remaining'];
        }

        $failedCount = 0;

        foreach ($tasks as $task) {
            if ($task->status === 'failed') {
                $failedCount++;
            }

            $task->update([
                'status' => 'queued',
                'attempt_count' => 0,
                'last_error' => null,
                'next_retry_at' => null,
                'locked_at' => null,
                'lock_token' => null,
            ]);

            PublishScheduledSidebarBlogrollJob::dispatch(
                $task->id,
                (int) ($task->dispatch_generation ?? 0)
            )->onQueue('scheduled_sidebar_campaigns');
        }

        if ($failedCount > 0) {
            $counters->accountForFailedTaskRetries($campaign, $failedCount);
        }

        $counters->syncCampaignFromTasks($campaign->fresh());

        return [
            'queued' => $tasks->count(),
            'skipped' => false,
            'reason' => null,
        ];
    }
}
