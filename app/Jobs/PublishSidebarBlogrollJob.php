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

use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignTask;
use App\Models\Admin\SidebarCampaignDomain;
use App\Models\Admin\SidebarCampaignLink;

class PublishSidebarBlogrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5; // ✅ keep 1 (we manage retries ourselves)

    public function __construct(public int $taskId)
    {
        $this->onQueue('sidebar_campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec  = 180; // 3 minutes
        $maxAttempts = 5;
        $baseBackoff = 60;  // seconds (1m, 2m, 4m, 8m...)

        $lockToken = (string) Str::uuid();

        // 🔐 STEP 1: Claim task safely (like your PublishCampaignPostJob)
        $task = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $t = SidebarCampaignTask::query()
                ->with('campaign')
                ->lockForUpdate()
                ->find($this->taskId);

            if (!$t) return null;

            if (in_array($t->status, ['success', 'failed'], true)) return null;
            if ($t->campaign && in_array($t->campaign->status, ['paused', 'cancelled'], true)) return null;

            if ($t->next_retry_at && $t->next_retry_at->isFuture()) return null;

            if ($t->locked_at && $t->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                return null;
            }

            $t->status     = 'publishing';
            $t->locked_at  = now();
            $t->lock_token = $lockToken;
            $t->started_at = $t->started_at ?: now();
            $t->save();

            return $t;
        });

        if (!$task) return;

        // Load relations needed
        $task->load([
            'campaign',
            'domainRow.domain',
            'linkRow',
        ]);

        try {
            // 🧠 STEP 2: Prepare endpoint + payload
            $domain = $task->domainRow?->domain;
            $link   = $task->linkRow;

            if (!$domain || !$link) {
                throw new \Exception("Missing domain/link relation for task_id={$task->id}");
            }

            $apiKey = $domain->api_key ?? null;
            if (!$apiKey) {
                throw new \Exception("Domain api_key missing domain_id={$domain->id}");
            }

            $base = trim((string) ($domain->name ?? ''));
            if ($base === '') {
                throw new \Exception("Domain name missing domain_id={$domain->id}");
            }
            if (!preg_match('~^https?://~i', $base)) {
                $base = 'https://' . $base;
            }

            $endpoint = rtrim($base, '/') . '/wp-json/external/v1/blogroll/add';
            $nofollow = (bool) ($link->nofollow ?? false);
            $sponsored = (bool) ($link->sponsored ?? false);
            $rel = [];
            if ($nofollow) {
                $rel[] = 'nofollow';
            }
            if ($sponsored) {
                $rel[] = 'sponsored';
            }
            $relString = implode(' ', $rel);

            // ✅ UTF-8 Sanitization - clean malformed bytes
            $keyword = cleanUtf8((string) $link->anchor_keyword, [
                'context' => 'sidebar_blogroll_api',
                'task_id' => $task->id,
                'field' => 'keyword',
            ]);

            $targetUrl = cleanUtf8((string) $link->target_url, [
                'context' => 'sidebar_blogroll_api',
                'task_id' => $task->id,
                'field' => 'link',
            ]);

            $payload = [
                'keyword' => $keyword,
                'link'    => $targetUrl,
                'api_key' => (string) $apiKey, // ✅ if your API requires it
                // Send both keys for compatibility across remote plugin versions.
                'nofollow' => $nofollow ? 1 : 0,
                'no_follow' => $nofollow ? 1 : 0,
                'sponsored' => $sponsored ? 1 : 0,
                'sponsor' => $sponsored ? 1 : 0,
                'rel' => $rel,
                'rel_attr' => $relString,
            ];

            // 🌐 STEP 3: Send request with UTF-8 safe headers
            $res = Http::withoutVerifying()
                ->timeout(180)
                ->acceptJson()
                ->contentType('application/json; charset=utf-8')
                ->withBody(safeJsonEncode($payload), 'application/json; charset=utf-8')
                ->post($endpoint);

            if (!$res->successful()) {
                throw new \Exception("WP blogroll API failed ({$res->status()}): " . $res->body());
            }

            $json = $res->json();
            $remoteId = null;

            if (
                is_array($json)
                && isset($json['data'][0]['id'])
            ) {
                $remoteId = (string) $json['data'][0]['id'];
            }
            if (!is_array($json)) {
                $json = ['raw' => $res->body()];
            }

            // ✅ STEP 4: Mark success
            DB::transaction(function () use ($task, $json, $res, $remoteId) {

                $fresh = SidebarCampaignTask::lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->lock_token !== $task->lock_token) return;

                $fresh->status         = 'success';
                $fresh->remote_id      = $remoteId;
                $fresh->http_status    = $res->status();
                $fresh->remote_response = $json; // or json_encode if your column is longtext
                $fresh->published_at   = now();
                $fresh->finished_at    = now();
                $fresh->last_error     = null;
                $fresh->next_retry_at  = null;
                $fresh->locked_at      = null;
                $fresh->lock_token     = null;
                $fresh->save();

                // 🔒 LOCK campaign row FIRST
                $campaign = SidebarCampaign::lockForUpdate()->find($fresh->sidebar_campaign_id);

                if (!$campaign) {
                    return;
                }

                // ✅ Check completion
                if ($campaign->completed_targets >= $campaign->total_targets) {
                    $campaign->status = 'completed';
                }

                // ➕ Increment locally
                $campaign->completed_targets++;

                if ($campaign->completed_targets + $campaign->failed_targets > $campaign->total_targets) {
                    $campaign->failed_targets--;
                }
                $campaign->save();

            });
        } catch (Throwable $e) {

            // ❌ STEP 5: Retry or fail (same as your campaign job)
            DB::transaction(function () use ($task, $e, $maxAttempts, $baseBackoff) {

                $fresh = SidebarCampaignTask::lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->lock_token !== $task->lock_token) return;

                $fresh->attempt_count++;
                $fresh->last_error = $e->getMessage();

                if ($fresh->attempt_count < $maxAttempts) {

                    $fresh->status = 'queued';

                    $delay = (int) ($baseBackoff * (2 ** ($fresh->attempt_count - 1)));
                    $delay = min($delay, 3600); // max 1 hour

                    $fresh->next_retry_at = now()->addSeconds($delay);
                    $fresh->locked_at  = null;
                    $fresh->lock_token = null;
                    $fresh->save();

                    // ✅ re-dispatch job with delay
                    PublishSidebarBlogrollJob::dispatch($fresh->id)
                        ->onQueue('sidebar_campaigns')
                        ->delay(now()->addSeconds($delay));
                } else {

                    $fresh->status = 'failed';
                    $fresh->next_retry_at = null;
                    $fresh->locked_at  = null;
                    $fresh->lock_token = null;
                    $fresh->finished_at = now();
                    $fresh->save();

                    // SidebarCampaign::whereKey($fresh->sidebar_campaign_id)
                    //     ->increment('failed_targets');

                    $campaign = SidebarCampaign::lockForUpdate()->find($fresh->sidebar_campaign_id);

                    if (!$campaign) return;

                    $currentTotal = $campaign->completed_targets + $campaign->failed_targets; //

                    // Only increment if it will not exceed total_targets
                    if ($currentTotal < $campaign->total_targets) {

                        $campaign->failed_targets++;

                        // Optional status update
                        if ($campaign->completed_targets > 0) {
                            $campaign->status = 'semi_failed';
                        } else {
                            $campaign->status = 'failed';
                        }

                        $campaign->save();
                    }
                }
            });
        } finally {
            $this->finalizeSidebarCampaignIfDone($task->sidebar_campaign_id);
        }
    }

    private function finalizeSidebarCampaignIfDone(int $campaignId): void
    {
        $campaign = SidebarCampaign::find($campaignId);
        if (!$campaign) return;

        $totalDone = $campaign->completed_targets + $campaign->failed_targets;

        if ($totalDone < $campaign->total_targets) return;

        $campaign->finished_at = now();

        if ($campaign->failed_targets === 0) {
            $campaign->status = 'completed';
        } elseif ($campaign->completed_targets > 0) {
            $campaign->status = 'semi_failed';
        } else {
            $campaign->status = 'failed';
        }

        $campaign->save();
    }
}
