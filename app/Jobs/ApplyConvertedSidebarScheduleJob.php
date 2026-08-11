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

class ApplyConvertedSidebarScheduleJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedSidebarJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'sidebar_campaign_conversions';

    public int $tries = 5;

    public int $timeout = 300;

    public function __construct(public int $scheduleTaskId)
    {

        $this->onQueue(self::QUEUE);

    }

    public function displayName(): string
    {

        $task = ScheduleSidebarCampaignTask::query()

            ->with('campaign:id,campaign_no')

            ->find($this->scheduleTaskId);

        $campaignLabel = $task?->campaign

            ? $this->dripfeedSidebarCampaignLabel((int) $task->schedule_sidebar_campaign_id)

            : 'unknown sidebar campaign';

        $remote = $task?->remote_id ? " blogroll#{$task->remote_id}" : '';

        return "Sidebar Live→Schedule: Publish blogroll — {$campaignLabel} (task #{$this->scheduleTaskId}{$remote})";

    }

    public function backoff(): array
    {

        return [60, 120, 300, 600, 900];

    }

    public function handle(BlogrollStatusApiService $api, ScheduleSidebarCampaignTargetCounterService $counters): void
    {

        $task = ScheduleSidebarCampaignTask::query()

            ->with(['campaign', 'domain.domain'])

            ->find($this->scheduleTaskId);

        if (! $task || ! $task->is_converted_live) {

            return;

        }

        if ($task->conversion_phase === 'published' && $task->status === 'success') {

            return;

        }

        if (! in_array($task->conversion_phase, ['drafted', 'scheduled_publish'], true)) {

            return;

        }

        if (! filled($task->remote_id)) {

            $this->failTask($task, 'Missing remote_id.', $counters);

            return;

        }

        $domain = $task->domain?->domain;

        if (! $domain || ! filled($domain->api_key)) {

            $this->failTask($task, 'Domain or API key missing.', $counters);

            return;

        }

        $slotDate = Carbon::parse(
            ScheduleSidebarReportStatus::slotDate($task) ?? $task->schedule_at ?? now()
        )->startOfDay();

        if ($slotDate->gt(now()->startOfDay())) {

            return;

        }

        $publish = $api->publishEntry((string) $domain->name, (string) $domain->api_key, (string) $task->remote_id);

        $task->last_conversion_attempt_at = now();

        if (! $publish['ok']) {

            $this->failTask($task, $publish['message'], $counters);

            return;

        }

        $snapshot = $api->fetchEntry((string) $domain->name, (string) $domain->api_key, (string) $task->remote_id);

        if (! $snapshot['ok']) {

            $this->failTask($task, 'Publish sent but remote verify failed: '.$snapshot['message'], $counters);

            return;

        }

        $remoteStatus = $snapshot['status'];

        if ($remoteStatus === 'draft') {

            $this->failTask($task, 'Remote blogroll entry is still draft after conversion.', $counters);

            return;

        }

        if ($remoteStatus !== 'publish') {

            $this->failTask($task, 'Unexpected remote blogroll status after conversion: '.($remoteStatus ?? 'unknown'), $counters);

            return;

        }

        $task->remote_status = $remoteStatus;

        if ($snapshot['remote_url']) {

            $task->remote_url = $snapshot['remote_url'];

        } else {

            $fromUpdate = $api->extractFromMutationResponse($publish['body'] ?? null);

            if ($fromUpdate['remote_url']) {

                $task->remote_url = $fromUpdate['remote_url'];

            }

        }

        $task->conversion_phase = 'published';

        $task->status = 'success';

        $task->published_at = now();

        $task->last_conversion_error = null;

        $task->last_error = null;

        $task->remote_response = $snapshot['body'];

        $task->save();

        $campaign = $task->campaign;

        if ($campaign) {

            $counters->syncCampaignFromTasks($campaign);

        }

    }

    private function failTask(ScheduleSidebarCampaignTask $task, string $message, ScheduleSidebarCampaignTargetCounterService $counters): void
    {

        $task->conversion_phase = 'failed';

        $task->status = 'failed';

        $task->last_conversion_error = $message;

        $task->last_error = $message;

        $task->last_conversion_attempt_at = now();

        $task->save();

        $campaign = $task->campaign ?? ScheduleSidebarCampaign::find($task->schedule_sidebar_campaign_id);

        if ($campaign) {

            $counters->syncCampaignFromTasks($campaign);

        }

    }
}
