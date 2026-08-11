<?php

namespace App\Jobs;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PublishScheduledSidebarBlogrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $scheduleTaskId, public int $dispatchGeneration = 0)
    {
        $this->onQueue('scheduled_sidebar_campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec = config('campaign.jobs.lock_ttl_seconds');
        $maxAttempts = config('campaign.jobs.max_internal_retries');
        $baseBackoff = config('campaign.jobs.base_backoff_seconds');
        $lockToken = (string) Str::uuid();

        /* =====================================================
         | STEP 1: LOCK SCHEDULE TASK
         ===================================================== */
        $task = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $t = ScheduleSidebarCampaignTask::lockForUpdate()
                ->with(['campaign', 'domain.domain', 'link'])
                ->find($this->scheduleTaskId);

            if (! $t) {
                return null;
            }

            if ((int) $t->dispatch_generation !== $this->dispatchGeneration) {
                return null;
            }

            if (in_array($t->status, ['publishing', 'success', 'failed'], true)) {
                return null;
            }

            if ($t->schedule_at->isFuture()) {
                return null;
            }

            if ($t->locked_at && $t->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                return null;
            }

            $t->status = 'publishing';
            $t->locked_at = now();
            $t->lock_token = $lockToken;
            $t->save();

            ScheduleSidebarCampaign::whereKey($t->schedule_sidebar_campaign_id)
                ->whereNull('started_at')
                ->update(['started_at' => now(), 'status' => 'running']);

            return $t;
        });

        if (! $task) {
            return;
        }

        try {
            /* =====================================================
             | STEP 2: LOAD DOMAIN + LINK DATA
             ===================================================== */
            $scheduledDomain = $task->domain; // ScheduleSidebarCampaignDomain
            $domain = $scheduledDomain?->domain; // Domain
            $link = $task->link;   // ScheduleSidebarCampaignLink

            if (! $domain || ! $link) {
                throw new \Exception(
                    "Missing domain or link for schedule_task_id={$task->id}"
                );
            }

            $apiKey = $domain->api_key ?? null;
            if (! $apiKey) {
                throw new \Exception("API key missing for domain_id={$domain->id}");
            }

            $base = trim((string) $domain->name);
            if ($base === '') {
                throw new \Exception("Domain name missing for domain_id={$domain->id}");
            }

            if (! preg_match('~^https?://~i', $base)) {
                $base = 'https://'.$base;
            }

            $endpoint = rtrim($base, '/').'/wp-json/external/v1/blogroll/add';

            // ✅ Always read boolean fields (needed for payload)
            $nofollow = (bool) ($link->nofollow ?? false);
            $sponsored = (bool) ($link->sponsored ?? false);
            $ugc = (bool) ($link->ugc ?? false);
            $noopener = (bool) ($link->noopener ?? false);
            $noreferrer = (bool) ($link->noreferrer ?? false);

            // ✅ Check if raw_rel_attr is available (from raw anchor mode)
            $rawRelAttr = trim((string) ($link->raw_rel_attr ?? ''));

            if ($rawRelAttr !== '') {
                // ✅ Use the complete rel string from raw HTML (supports ANY rel values)
                $relString = $rawRelAttr;
                $rel = array_filter(array_map('trim', explode(' ', $rawRelAttr)));
            } else {
                // ✅ Fall back to building from individual boolean fields (backward compatibility)
                $rel = [];
                if ($nofollow) {
                    $rel[] = 'nofollow';
                }
                if ($sponsored) {
                    $rel[] = 'sponsored';
                }
                if ($ugc) {
                    $rel[] = 'ugc';
                }
                if ($noopener) {
                    $rel[] = 'noopener';
                }
                if ($noreferrer) {
                    $rel[] = 'noreferrer';
                }
                $relString = implode(' ', $rel);
            }

            // ✅ Handle both single and JSON array types
            $urlType = $link->target_url_type ?? 'single';
            $kwType = $link->anchor_keyword_type ?? 'single';

            $urls = [];
            $keywords = [];

            if ($urlType === 'json') {
                $decoded = json_decode($link->target_url, true);
                $urls = is_array($decoded) ? $decoded : [$link->target_url];
            } else {
                $urls = [$link->target_url];
            }

            if ($kwType === 'json') {
                $decoded = json_decode($link->anchor_keyword, true);
                $keywords = is_array($decoded) ? $decoded : [$link->anchor_keyword];
            } else {
                $keywords = [$link->anchor_keyword];
            }

            // ✅ Send multiple links to WordPress (one per keyword/URL pair)
            $remoteIds = [];
            $responses = [];

            $pairCount = max(count($urls), count($keywords));
            for ($i = 0; $i < $pairCount; $i++) {
                $targetUrl = $urls[$i] ?? $urls[0] ?? '';
                $keyword = $keywords[$i] ?? $keywords[0] ?? '';

                $payload = [
                    'keyword' => (string) $keyword,
                    'link' => (string) $targetUrl,
                    'api_key' => (string) $apiKey,
                    'nofollow' => $nofollow ? 1 : 0,
                    'no_follow' => $nofollow ? 1 : 0,
                    'sponsored' => $sponsored ? 1 : 0,
                    'sponsor' => $sponsored ? 1 : 0,
                    'ugc' => $ugc ? 1 : 0,
                    'noopener' => $noopener ? 1 : 0,
                    'noreferrer' => $noreferrer ? 1 : 0,
                    'rel' => $rel,
                    'rel_attr' => $relString,
                ];

                // 🐛 DEBUG: Log payload to verify rel attributes are sent correctly
                \Log::info('Scheduled Sidebar Blogroll Payload Debug', [
                    'task_id' => $task->id,
                    'domain' => $domain->name,
                    'rel_array' => $rel,
                    'rel_string' => $relString,
                    'full_payload' => $payload,
                ]);

                /* =====================================================
                 | STEP 3: CALL WORDPRESS API
                 ===================================================== */
                $res = Http::withoutVerifying()
                    ->timeout(180)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, $payload);

                if (! $res->successful()) {
                    throw new \Exception(
                        "WP blogroll API failed ({$res->status()}): ".$res->body()
                    );
                }

                $json = is_array($res->json()) ? $res->json() : ['raw' => $res->body()];
                $remoteId = $json['data'][0]['id'] ?? null;

                $remoteIds[] = $remoteId;
                $responses[] = $json;
            }

            // ✅ Store first remote ID (or JSON array of all IDs)
            $finalRemoteId = count($remoteIds) === 1 ? $remoteIds[0] : json_encode($remoteIds);
            $finalResponse = count($responses) === 1 ? $responses[0] : ['multiple' => $responses];

            /* =====================================================
             | STEP 4: MARK SUCCESS
             ===================================================== */
            DB::transaction(function () use ($task, $finalResponse, $finalRemoteId) {

                $fresh = ScheduleSidebarCampaignTask::lockForUpdate()->find($task->id);
                if (! $fresh || $fresh->lock_token !== $task->lock_token) {
                    return;
                }

                $fresh->status = 'success';
                $fresh->remote_id = $finalRemoteId;
                $fresh->http_status = 200;
                $fresh->remote_response = $finalResponse;
                $fresh->published_at = now();
                $fresh->locked_at = null;
                $fresh->lock_token = null;
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
                if (! $fresh || $fresh->lock_token !== $task->lock_token) {
                    return;
                }

                $fresh->attempt_count++;
                $fresh->last_error = $e->getMessage();

                if ($fresh->attempt_count < $maxAttempts) {

                    $delay = min(
                        $baseBackoff * (2 ** ($fresh->attempt_count - 1)),
                        3600
                    );

                    $fresh->status = 'queued';
                    $fresh->next_retry_at = now()->addSeconds($delay);
                    $fresh->locked_at = null;
                    $fresh->lock_token = null;
                    $fresh->save();

                    PublishScheduledSidebarBlogrollJob::dispatch($fresh->id, (int) $fresh->dispatch_generation)
                        ->delay(now()->addSeconds($delay))
                        ->onQueue('scheduled_sidebar_campaigns');
                } else {

                    $fresh->status = 'failed';
                    $fresh->locked_at = null;
                    $fresh->lock_token = null;
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
            if (! $campaign) {
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
