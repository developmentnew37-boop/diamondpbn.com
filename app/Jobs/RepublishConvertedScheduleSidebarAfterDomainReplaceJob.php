<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RepublishConvertedScheduleSidebarAfterDomainReplaceJob implements ShouldQueue
{
    use Concerns\ShowsLiveToDripfeedSidebarJobLabel;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $scheduleTaskId,
        public int $dispatchGeneration = 0,
    ) {
        $this->onQueue(DraftConvertedLiveSidebarTasksJob::QUEUE);
    }

    public function displayName(): string
    {
        return "Sidebar Live→Schedule: Republish after domain replace — task #{$this->scheduleTaskId}";
    }

    public function handle(ScheduleSidebarCampaignTargetCounterService $counters): void
    {
        $task = ScheduleSidebarCampaignTask::query()
            ->with(['domain.domain', 'link', 'campaign'])
            ->find($this->scheduleTaskId);

        if (! $task || ! $task->is_converted_live) {
            return;
        }

        if ((int) $task->dispatch_generation !== $this->dispatchGeneration) {
            return;
        }

        $domain = $task->domain?->domain;
        $link = $task->link;

        if (! $domain || ! $link || ! filled($domain->api_key)) {
            $this->markFailed($task, 'Replacement domain, link, or API key missing.', $counters);

            return;
        }

        try {
            [$keyword, $targetUrl, $rel] = $this->resolveLinkPair($link);

            $base = trim((string) $domain->name);
            if (! preg_match('~^https?://~i', $base)) {
                $base = 'https://'.$base;
            }

            $endpoint = rtrim($base, '/').'/wp-json/external/v1/blogroll/add';
            $payload = [
                'keyword' => cleanUtf8($keyword, ['context' => 'wp_api_blogroll', 'field' => 'keyword']),
                'link' => $targetUrl,
                'url' => $targetUrl,
                'rel' => $rel,
                'api_key' => (string) $domain->api_key,
            ];

            $rawRel = trim((string) ($link->raw_rel_attr ?? ''));
            if ($rawRel !== '') {
                $payload['rel_attr'] = $rawRel;
            }

            $response = Http::withoutVerifying()
                ->timeout(120)
                ->acceptJson()
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                throw new \RuntimeException("Blogroll API failed ({$response->status()}): ".$response->body());
            }

            $json = $response->json();
            if (! is_array($json)) {
                throw new \RuntimeException('Blogroll API returned non-JSON response.');
            }

            $remoteId = $json['id'] ?? $json['data']['id'] ?? null;
            if (! filled($remoteId)) {
                throw new \RuntimeException('Blogroll API did not return link id.');
            }

            $task->forceFill([
                'remote_id' => (string) $remoteId,
                'remote_url' => $targetUrl,
                'remote_status' => 'publish',
                'http_status' => $response->status(),
                'remote_response' => $json,
                'published_at' => now(),
                'conversion_phase' => 'pending_draft',
                'status' => 'queued',
                'last_error' => null,
                'last_conversion_error' => null,
                'last_conversion_attempt_at' => null,
            ])->save();

            DraftConvertedLiveSidebarTasksJob::dispatch([$task->id], (int) $task->schedule_sidebar_campaign_id)
                ->onQueue(DraftConvertedLiveSidebarTasksJob::QUEUE);

            if ($task->campaign) {
                $counters->syncCampaignFromTasks($task->campaign);
            }
        } catch (Throwable $exception) {
            Log::warning('RepublishConvertedScheduleSidebarAfterDomainReplaceJob failed', [
                'schedule_task_id' => $task->id,
                'error' => $exception->getMessage(),
            ]);

            $this->markFailed($task, $exception->getMessage(), $counters);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, string>}
     */
    private function resolveLinkPair(ScheduleSidebarCampaignLink $link): array
    {
        $urlType = $link->target_url_type ?? 'single';
        $kwType = $link->anchor_keyword_type ?? 'single';

        $urls = $urlType === 'json'
            ? (json_decode((string) $link->target_url, true) ?: [])
            : [(string) $link->target_url];

        $keywords = $kwType === 'json'
            ? (json_decode((string) $link->anchor_keyword, true) ?: [])
            : [(string) $link->anchor_keyword];

        $targetUrl = trim((string) ($urls[0] ?? ''));
        $keyword = trim((string) ($keywords[0] ?? ''));

        if ($targetUrl === '' || $keyword === '') {
            throw new \RuntimeException('Link keyword or URL missing.');
        }

        $rawRelAttr = trim((string) ($link->raw_rel_attr ?? ''));
        if ($rawRelAttr !== '') {
            $rel = array_values(array_filter(array_map('trim', explode(' ', $rawRelAttr))));
        } else {
            $rel = [];
            if ($link->nofollow ?? false) {
                $rel[] = 'nofollow';
            }
            if ($link->sponsored ?? false) {
                $rel[] = 'sponsored';
            }
            if ($link->ugc ?? false) {
                $rel[] = 'ugc';
            }
            if ($link->noopener ?? false) {
                $rel[] = 'noopener';
            }
            if ($link->noreferrer ?? false) {
                $rel[] = 'noreferrer';
            }
        }

        return [$keyword, $targetUrl, $rel];
    }

    private function markFailed(
        ScheduleSidebarCampaignTask $task,
        string $message,
        ScheduleSidebarCampaignTargetCounterService $counters,
    ): void {
        $task->forceFill([
            'conversion_phase' => 'failed',
            'status' => 'failed',
            'last_error' => $message,
            'last_conversion_error' => $message,
            'last_conversion_attempt_at' => now(),
        ])->save();

        if ($task->campaign) {
            $counters->syncCampaignFromTasks($task->campaign);
        }
    }
}
