<?php

namespace App\Services;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use Illuminate\Database\Eloquent\Builder;

class ScheduleSidebarCampaignTargetCounterService
{
    /**
     * @return array{total: int, completed: int, failed: int, pending: int}
     */
    public function countTasksForCampaign(ScheduleSidebarCampaign $campaign, bool $convertedLiveOnly = false): array
    {
        $useConvertedOnly = $convertedLiveOnly || filled($campaign->converted_from_sidebar_campaign_id);

        $query = ScheduleSidebarCampaignTask::query()
            ->where('schedule_sidebar_campaign_id', $campaign->id);

        if ($useConvertedOnly) {
            $query->where('is_converted_live', true);
        }

        $total = (int) (clone $query)->count();
        $completed = (int) (clone $query)->where('status', 'success')->count();
        $failed = (int) (clone $query)->where('status', 'failed')->count();
        $pending = max($total - ($completed + $failed), 0);

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
        ];
    }

    public function syncCampaignFromTasks(ScheduleSidebarCampaign $campaign): ScheduleSidebarCampaign
    {
        $counts = $this->countTasksForCampaign($campaign);
        $total = $counts['total'];

        if ($total < 1 && filled($campaign->converted_from_sidebar_campaign_id)) {
            $total = (int) $campaign->total_targets;
        } elseif ($total > 0) {
            $campaign->total_targets = $total;
        }

        $completed = $counts['completed'];
        $failed = $counts['failed'];
        $pending = max($total - ($completed + $failed), 0);

        $status = $this->deriveStatusFromCounters($total, $completed, $failed, $pending);

        $updates = [
            'completed_targets' => $completed,
            'failed_targets' => $failed,
            'status' => $status,
        ];

        if ($total > 0) {
            $updates['total_targets'] = $total;
        }

        if (in_array($status, ['completed', 'semi_failed', 'failed'], true)) {
            $updates['finished_at'] = $campaign->finished_at ?? now();
        } elseif (in_array($status, ['queued', 'running'], true)) {
            $updates['finished_at'] = null;
        }

        if (filled($campaign->converted_from_sidebar_campaign_id)) {
            $updates['conversion_pipeline_status'] = $this->deriveConversionPipelineStatus(
                $campaign,
                $total,
                $completed,
                $failed,
                $pending
            );
        }

        $campaign->update($updates);

        return $campaign->fresh();
    }

    /**
     * When retrying failed tasks: drop their failed_targets and reopen the campaign.
     */
    public function accountForFailedTaskRetries(ScheduleSidebarCampaign $campaign, int $failedTasksRetried): ScheduleSidebarCampaign
    {
        if ($failedTasksRetried < 1) {
            return $campaign;
        }

        $campaign->refresh();
        $decrement = min($failedTasksRetried, (int) $campaign->failed_targets);
        if ($decrement > 0) {
            $campaign->failed_targets = max(0, (int) $campaign->failed_targets - $decrement);
        }

        if (in_array((string) $campaign->status, ['completed', 'semi_failed', 'failed', 'queued'], true)) {
            $campaign->status = 'running';
            $campaign->finished_at = null;
        }

        $campaign->save();

        return $campaign->fresh();
    }

    /**
     * List / DB status from counters (pending already clamped with max(..., 0)).
     */
    public function deriveStatusFromCounters(int $total, int $completed, int $failed, int $pending): string
    {
        if ($pending > 0 && ($completed > 0 || $failed > 0)) {
            return 'running';
        }

        if ($total > 0 && $failed === $total && $completed === 0) {
            return 'failed';
        }

        if ($total > 0 && $pending === 0 && $failed === 0 && $completed >= $total) {
            return 'completed';
        }

        if ($total > 0 && $pending === 0 && $failed > 0 && $completed > 0) {
            return 'semi_failed';
        }

        if ($total > 0 && ($completed + $failed) > $total && $completed > 0 && $failed > 0) {
            return 'semi_failed';
        }

        return 'queued';
    }

    /**
     * @return array{total: int, active: int, completed: int, attention: int}
     */
    public function convertedRegistryStats(Builder $campaignQuery): array
    {
        $ids = (clone $campaignQuery)->select($campaignQuery->getModel()->getTable().'.id')->pluck('id');

        if ($ids->isEmpty()) {
            return ['total' => 0, 'active' => 0, 'completed' => 0, 'attention' => 0];
        }

        $rows = ScheduleSidebarCampaignTask::query()
            ->whereIn('schedule_sidebar_campaign_id', $ids)
            ->where('is_converted_live', true)
            ->selectRaw('schedule_sidebar_campaign_id')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('schedule_sidebar_campaign_id')
            ->get()
            ->keyBy('schedule_sidebar_campaign_id');

        $active = 0;
        $completed = 0;
        $attention = 0;

        foreach ($ids as $id) {
            $row = $rows->get($id);
            if (! $row) {
                continue;
            }

            $total = (int) $row->total;
            $done = (int) $row->completed;
            $fail = (int) $row->failed;
            $pending = max($total - ($done + $fail), 0);

            if ($pending > 0) {
                $active++;
            } elseif ($total > 0 && $fail === 0 && $done >= $total) {
                $completed++;
            } elseif ($fail > 0) {
                $attention++;
            }
        }

        return [
            'total' => $ids->count(),
            'active' => $active,
            'completed' => $completed,
            'attention' => $attention,
        ];
    }

    private function deriveConversionPipelineStatus(
        ScheduleSidebarCampaign $campaign,
        int $total,
        int $completed,
        int $failed,
        int $pending,
    ): string {
        if ($pending > 0) {
            $pendingDraft = ScheduleSidebarCampaignTask::query()
                ->where('schedule_sidebar_campaign_id', $campaign->id)
                ->where('is_converted_live', true)
                ->where('conversion_phase', 'pending_draft')
                ->exists();

            return $pendingDraft ? 'drafting' : 'scheduling';
        }

        if ($total > 0 && $completed >= $total && $failed === 0) {
            return 'completed';
        }

        if ($failed > 0) {
            return 'semi_failed';
        }

        return $campaign->conversion_pipeline_status ?? 'scheduling';
    }
}
