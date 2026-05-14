<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

use App\Models\Admin\{
    ScheduleSidebarCampaign,
    ScheduleSidebarCampaignTask
};

class PublishScheduledSidebarBlogrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $scheduleTaskId)
    {
        $this->onQueue('scheduled_sidebar_campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec  = 180;
        $maxAttempts = 5;
        $baseBackoff = 60;
        $lockToken   = (string) Str::uuid();

        /* =====================================================
         | STEP 1: LOCK SCHEDULE TASK
         ===================================================== */
        $task = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $t = ScheduleSidebarCampaignTask::lockForUpdate()
                ->with(['campaign', 'domain.domain', 'link'])
                ->find($this->scheduleTaskId);

            if (!$t) return null;

            if (in_array($t->status, ['publishing', 'success', 'failed'], true)) {
                return null;
            }

            if ($t->schedule_at->isFuture()) return null;

            if ($t->locked_at && $t->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                return null;
            }

            $t->status     = 'publishing';
            $t->locked_at  = now();
            $t->lock_token = $lockToken;
            $t->save();

            ScheduleSidebarCampaign::whereKey($t->schedule_sidebar_campaign_id)
                ->whereNull('started_at')
                ->update(['started_at' => now(), 'status' => 'running']);

            return $t;
        });

        if (!$task) return;

        try {
            /* =====================================================
             | STEP 2: LOAD DOMAIN + LINK DATA
             ===================================================== */
            $scheduledDomain = $task->domain; // ScheduleSidebarCampaignDomain
            $domain          = $scheduledDomain?->domain; // Domain
            $link            = $task->link;   // ScheduleSidebarCampaignLink

            if (!$domain || !$link) {
                throw new \Exception(
                    "Missing domain or link for schedule_task_id={$task->id}"
                );
            }

            $apiKey = $domain->api_key ?? null;
            if (!$apiKey) {
                throw new \Exception("API key missing for domain_id={$domain->id}");
            }

            $base = trim((string) $domain->name);
            if ($base === '') {
                throw new \Exception("Domain name missing for domain_id={$domain->id}");
            }

            if (!preg_match('~^https?://~i', $base)) {
                $base = 'https://' . $base;
            }

            $endpoint = rtrim($base, '/') . '/wp-json/external/v1/blogroll/add';
            $nofollow = (bool) ($link->nofollow ?? false);

            $payload = [
                'keyword' => (string) $link->anchor_keyword,
                'link'    => (string) $link->target_url,
                'api_key' => (string) $apiKey,
                // Send both keys for compatibility across remote plugin versions.
                'nofollow' => $nofollow ? 1 : 0,
                'no_follow' => $nofollow ? 1 : 0,
                // Some remote blogroll plugins read string rel attributes.
                'rel' => $nofollow ? 'no-follow' : 'follow',
                'rel_attr' => $nofollow ? 'no-follow' : '',
            ];

            /* =====================================================
             | STEP 3: CALL WORDPRESS API
             ===================================================== */
            $res = Http::withoutVerifying()
                ->timeout(180)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if (!$res->successful()) {
                throw new \Exception(
                    "WP blogroll API failed ({$res->status()}): " . $res->body()
                );
            }

            $json     = is_array($res->json()) ? $res->json() : ['raw' => $res->body()];
            $remoteId = $json['data'][0]['id'] ?? null;

            /* =====================================================
             | STEP 4: MARK SUCCESS
             ===================================================== */
            DB::transaction(function () use ($task, $json, $remoteId, $res) {

                $fresh = ScheduleSidebarCampaignTask::lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->lock_token !== $task->lock_token) return;

                $fresh->status          = 'success';
                $fresh->remote_id       = $remoteId;
                $fresh->http_status     = $res->status();
                $fresh->remote_response = $json;
                $fresh->published_at    = now();
                $fresh->locked_at       = null;
                $fresh->lock_token      = null;
                $fresh->save();

                ScheduleSidebarCampaign::whereKey(
                    $fresh->schedule_sidebar_campaign_id
                )->increment('completed_targets');
            });
        } catch (Throwable $e) {

            /* =====================================================
             | STEP 5: RETRY OR FAIL
             ===================================================== */
            DB::transaction(function () use ($task, $e, $maxAttempts, $baseBackoff) {

                $fresh = ScheduleSidebarCampaignTask::lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->lock_token !== $task->lock_token) return;

                $fresh->attempt_count++;
                $fresh->last_error = $e->getMessage();

                if ($fresh->attempt_count < $maxAttempts) {

                    $delay = min(
                        $baseBackoff * (2 ** ($fresh->attempt_count - 1)),
                        3600
                    );

                    $fresh->status        = 'queued';
                    $fresh->next_retry_at = now()->addSeconds($delay);
                    $fresh->locked_at     = null;
                    $fresh->lock_token    = null;
                    $fresh->save();

                    PublishScheduledSidebarBlogrollJob::dispatch($fresh->id)
                        ->delay(now()->addSeconds($delay))
                        ->onQueue('scheduled_sidebar_campaigns');
                } else {

                    $fresh->status      = 'failed';
                    $fresh->locked_at   = null;
                    $fresh->lock_token  = null;
                    $fresh->save();

                    ScheduleSidebarCampaign::whereKey(
                        $fresh->schedule_sidebar_campaign_id
                    )->increment('failed_targets');
                }
            });
        } finally {
            $this->finalizeCampaignIfDone($task->schedule_sidebar_campaign_id);
        }
    }

    /**
     * When all tasks are done: set finished_at and campaign status (completed / semi_failed / failed).
     * Uses lock inside transaction to avoid race conditions when multiple jobs finish close together.
     */
    private function finalizeCampaignIfDone(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = ScheduleSidebarCampaign::lockForUpdate()->find($campaignId);
            if (!$campaign) {
                return;
            }

            $totalDone = $campaign->completed_targets + $campaign->failed_targets;
            if ($totalDone < $campaign->total_targets) {
                return;
            }

            $campaign->syncStatusFromCounts();
        });
    }
}
