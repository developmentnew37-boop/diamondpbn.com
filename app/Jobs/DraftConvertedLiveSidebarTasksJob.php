<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\BlogrollStatusApiService;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use App\Support\ScheduleSidebarReportStatus;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DraftConvertedLiveSidebarTasksJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedSidebarJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'sidebar_campaign_conversions';

    public int $tries = 3;

    public int $timeout = 3600;

    /**
     * @param  int[]  $scheduleTaskIds
     */
    public function __construct(

        public array $scheduleTaskIds,

        public int $scheduleSidebarCampaignId,

    ) {

        $this->onQueue(self::QUEUE);

    }

    public function displayName(): string
    {

        $count = count($this->scheduleTaskIds);

        $campaign = $this->dripfeedSidebarCampaignLabel($this->scheduleSidebarCampaignId);

        $sample = $count <= 3

            ? ' ['.implode(',', $this->scheduleTaskIds).']'

            : ' [tasks '.implode(',', array_slice($this->scheduleTaskIds, 0, 3)).',…]';

        return "Sidebar Live→Schedule: Draft {$count} blogroll task(s) — {$campaign}{$sample}";

    }

    public function handle(BlogrollStatusApiService $api, ScheduleSidebarCampaignTargetCounterService $counters): void
    {

        $campaign = ScheduleSidebarCampaign::find($this->scheduleSidebarCampaignId);

        if (! $campaign) {

            return;

        }

        $runDate = $campaign->conversion_run_date

            ? Carbon::parse($campaign->conversion_run_date)->startOfDay()

            : now()->startOfDay();

        $tasks = ScheduleSidebarCampaignTask::query()

            ->with(['domain.domain', 'campaign'])

            ->whereIn('id', $this->scheduleTaskIds)

            ->where('is_converted_live', true)

            ->get();

        foreach ($tasks as $task) {

            if ($task->conversion_phase !== 'pending_draft' && $task->conversion_phase !== 'failed') {

                continue;

            }

            if (! filled($task->remote_id)) {

                $this->markFailed($task, 'Missing remote_id for converted task.', $counters);

                continue;

            }

            $domain = $task->domain?->domain;

            if (! $domain || ! filled($domain->api_key)) {

                $this->markFailed($task, 'Domain or API key missing.', $counters);

                continue;

            }

            $result = $api->draftEntry((string) $domain->name, (string) $domain->api_key, (string) $task->remote_id);

            $task->last_conversion_attempt_at = now();

            if (! $result['ok']) {

                $this->markFailed($task, $result['message'], $counters);

                continue;

            }

            $task->conversion_phase = 'drafted';

            $task->remote_status = 'draft';

            $task->last_conversion_error = null;

            $task->save();

            $slotDate = Carbon::parse(
                ScheduleSidebarReportStatus::slotDate($task) ?? $task->schedule_at ?? $runDate
            )->startOfDay();

            if ($slotDate->lte($runDate)) {

                ApplyConvertedSidebarScheduleJob::dispatch($task->id)->onQueue(ApplyConvertedSidebarScheduleJob::QUEUE);

            }

        }

        $this->refreshPipelineStatus($campaign);

        $counters->syncCampaignFromTasks($campaign);

    }

    private function markFailed(ScheduleSidebarCampaignTask $task, string $message, ScheduleSidebarCampaignTargetCounterService $counters): void
    {

        $task->conversion_phase = 'failed';

        $task->last_conversion_error = $message;

        $task->last_conversion_attempt_at = now();

        $task->status = 'failed';

        $task->last_error = $message;

        $task->save();

        if ($task->campaign) {

            $counters->syncCampaignFromTasks($task->campaign);

        }

    }

    private function refreshPipelineStatus(ScheduleSidebarCampaign $campaign): void
    {

        $pending = ScheduleSidebarCampaignTask::query()

            ->where('schedule_sidebar_campaign_id', $campaign->id)

            ->where('is_converted_live', true)

            ->where('conversion_phase', 'pending_draft')

            ->count();

        if ($pending === 0) {

            $failed = ScheduleSidebarCampaignTask::query()

                ->where('schedule_sidebar_campaign_id', $campaign->id)

                ->where('is_converted_live', true)

                ->where('conversion_phase', 'failed')

                ->count();

            $campaign->update([

                'conversion_pipeline_status' => $failed > 0 ? 'semi_failed' : 'scheduling',

            ]);

        }

    }
}
