<?php

namespace App\Services;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Support\ConvertedLivePostSlot;
use App\Support\ScheduleSidebarReportStatus;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ConvertedSidebarRemoteSyncService
{
    public function __construct(
        private readonly BlogrollStatusApiService $api,
        private readonly ScheduleSidebarCampaignTargetCounterService $counters,
    ) {}

    public function needsRemoteSync(ScheduleSidebarCampaignTask $task): bool
    {
        if (! $task->is_converted_live || ! filled($task->remote_id)) {
            return false;
        }

        if (! $task->domain?->domain?->api_key) {
            return false;
        }

        if ($task->conversion_phase === 'drafted'
            && ConvertedLivePostSlot::isDue(ScheduleSidebarReportStatus::slotDate($task))) {
            return true;
        }

        $remote = strtolower(trim((string) ($task->remote_status ?? '')));

        if ($remote === '') {
            return true;
        }

        if ($task->status === 'success' && ! in_array($remote, ['publish', 'draft'], true)) {
            return true;
        }

        if (in_array($task->status, ['queued', 'publishing'], true) && $remote === 'publish') {
            return true;
        }

        return false;
    }

    public function alignLocalStatusFromStoredRemote(ScheduleSidebarCampaignTask $task): bool
    {
        if (! $task->is_converted_live) {
            return false;
        }

        $remote = strtolower(trim((string) ($task->remote_status ?? '')));
        if ($remote === '') {
            return false;
        }

        return $this->applyRemoteSnapshot($task, [
            'ok' => true,
            'status' => $remote,
            'remote_url' => $task->remote_url,
        ]);
    }

    public function syncCampaignTasks(ScheduleSidebarCampaign $campaign): int
    {
        if (! filled($campaign->converted_from_sidebar_campaign_id)) {
            return 0;
        }

        $allTasks = ScheduleSidebarCampaignTask::query()
            ->with(['domain.domain', 'campaign'])
            ->where('schedule_sidebar_campaign_id', $campaign->id)
            ->where('is_converted_live', true)
            ->orderBy('id')
            ->get();

        $updated = 0;

        foreach ($allTasks as $task) {
            if ($this->alignLocalStatusFromStoredRemote($task)) {
                $task->save();
                $updated++;
            }
        }

        $toFetch = $allTasks->filter(fn (ScheduleSidebarCampaignTask $task) => $this->needsRemoteSync($task));
        $updated += $this->syncTaskCollection($toFetch);

        if ($updated > 0) {
            $this->counters->syncCampaignFromTasks($campaign->fresh());
        }

        return $updated;
    }

    /**
     * @param  Collection<int, ScheduleSidebarCampaignTask>  $tasks
     */
    public function syncTaskCollection(Collection $tasks): int
    {
        if ($tasks->isEmpty()) {
            return 0;
        }

        $responses = Http::pool(function ($pool) use ($tasks) {
            foreach ($tasks as $task) {
                $domain = $task->domain?->domain;
                if (! $domain || ! filled($domain->api_key)) {
                    continue;
                }

                $baseUrl = $this->api->normalizeBaseUrl((string) $domain->name);
                $url = $baseUrl.'/wp-json/external/v1/blogroll/'.urlencode((string) $task->remote_id)
                    .'?api_key='.urlencode((string) $domain->api_key);

                $pool->as('task_'.$task->id)
                    ->withoutVerifying()
                    ->timeout(90)
                    ->acceptJson()
                    ->get($url);
            }
        });

        $updated = 0;

        foreach ($tasks as $task) {
            $key = 'task_'.$task->id;
            if (! isset($responses[$key])) {
                continue;
            }

            $response = $responses[$key];
            if (! $response instanceof Response) {
                continue;
            }

            $json = $response->json();
            $body = is_array($json) ? $json : null;

            if (! $response->successful() || ! is_array($body)) {
                continue;
            }

            $nested = is_array($body['data'] ?? null) ? $body['data'] : $body;
            $snapshot = [
                'ok' => true,
                'status' => $this->api->extractEntryStatus($nested),
                'remote_url' => $this->api->extractRemoteUrl($nested),
                'body' => $body,
            ];

            if ($this->applyRemoteSnapshot($task, $snapshot)) {
                $task->save();
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array{ok: bool, status: string|null, remote_url: string|null, body?: array<string, mixed>|null}  $snapshot
     */
    public function applyRemoteSnapshot(ScheduleSidebarCampaignTask $task, array $snapshot): bool
    {
        $remoteStatus = $snapshot['status'] ?? null;
        if (! is_string($remoteStatus) || $remoteStatus === '') {
            return false;
        }

        $remoteStatus = strtolower(trim($remoteStatus));
        $changed = false;

        if ($snapshot['remote_url'] && $snapshot['remote_url'] !== $task->remote_url) {
            $task->remote_url = $snapshot['remote_url'];
            $changed = true;
        }

        if ($remoteStatus !== $task->remote_status) {
            $task->remote_status = $remoteStatus;
            $changed = true;
        }

        if (isset($snapshot['body']) && is_array($snapshot['body'])) {
            $task->remote_response = $snapshot['body'];
            $changed = true;
        }

        $slotDue = ConvertedLivePostSlot::isDue(self::slotDate($task));

        if ($remoteStatus === 'draft' && $task->status === 'success') {
            $task->status = 'failed';
            $task->conversion_phase = 'failed';
            $task->last_error = 'Blogroll entry is still draft (not visible).';
            $task->last_conversion_error = $task->last_error;
            $changed = true;
        } elseif ($remoteStatus === 'publish') {
            if ($slotDue) {
                if ($task->status !== 'success') {
                    $task->status = 'success';
                    $changed = true;
                }
                if ($task->conversion_phase !== 'published') {
                    $task->conversion_phase = 'published';
                    $changed = true;
                }
                $task->last_error = null;
                $task->last_conversion_error = null;
                if (! $task->published_at) {
                    $task->published_at = now();
                    $changed = true;
                }
            } elseif ($task->status === 'success') {
                $task->status = 'queued';
                $task->conversion_phase = $task->conversion_phase === 'published' ? 'drafted' : $task->conversion_phase;
                $changed = true;
            }
        }

        return $changed;
    }

    private static function slotDate(ScheduleSidebarCampaignTask $task): mixed
    {
        return ScheduleSidebarReportStatus::slotDate($task);
    }
}
