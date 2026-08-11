<?php

namespace App\Services;

use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignTask;
use Illuminate\Database\Eloquent\Builder;

class SidebarConversionEligibilityService
{
    public const MIN_AGENT_VERSION = '8.3.0';

    public function campaignQueryForAdmin(int $adminId, bool $isSuperAdmin): Builder
    {

        $q = SidebarCampaign::query()

            ->whereNull('converted_to_schedule_sidebar_campaign_id')

            ->whereIn('status', ['completed', 'semi_failed', 'running', 'failed', 'queued', 'paused']);

        if (! $isSuperAdmin) {

            $q->where('admin_id', $adminId);

        }

        return $q;

    }

    /**
     * @return array{

     *   convertible: int,

     *   failed: int,

     *   in_progress: int,

     *   total_tasks: int,

     *   failed_task_ids: int[],

     *   all_live: bool,

     *   can_skip_recover: bool

     * }
     */
    public function summarize(SidebarCampaign $campaign): array
    {

        $tasks = SidebarCampaignTask::query()

            ->where('sidebar_campaign_id', $campaign->id)

            ->get(['id', 'status', 'remote_id']);

        $convertible = 0;

        $failed = 0;

        $inProgress = 0;

        $failedIds = [];

        foreach ($tasks as $task) {

            if ($this->isConvertibleTask($task)) {

                $convertible++;

            } elseif (in_array($task->status, ['queued', 'publishing'], true)) {

                $inProgress++;

            } elseif ($task->status === 'failed') {

                $failed++;

                $failedIds[] = (int) $task->id;

            }

        }

        $allLive = $failed === 0 && $inProgress === 0 && $convertible > 0;

        return [

            'convertible' => $convertible,

            'failed' => $failed,

            'in_progress' => $inProgress,

            'total_tasks' => $tasks->count(),

            'failed_task_ids' => $failedIds,

            'all_live' => $allLive,

            'can_skip_recover' => $allLive,

        ];

    }

    public function isConvertibleTask(SidebarCampaignTask $task): bool
    {

        return $task->status === 'success'

            && filled($task->remote_id);

    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SidebarCampaignTask>
     */
    public function convertibleTasks(SidebarCampaign $source)
    {

        return SidebarCampaignTask::query()

            ->where('sidebar_campaign_id', $source->id)

            ->where('status', 'success')

            ->whereNotNull('remote_id')

            ->where('remote_id', '!=', '')

            ->orderBy('id')

            ->get();

    }
}
