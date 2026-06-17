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

    public int $tries = 1;

    public function __construct(public int $taskId)
    {
        $this->onQueue('sidebar_campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec  = config('campaign.jobs.lock_ttl_seconds');
        $maxAttempts = config('campaign.jobs.max_internal_retries');
        $baseBackoff = config('campaign.jobs.base_backoff_seconds');

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

            // ✅ Always read boolean fields (needed for payload)
            $nofollow = (bool) ($link->nofollow ?? false);
            $sponsored = (bool) ($link->sponsored ?? false);
            $ugc = (bool) ($link->ugc ?? false);
            $noopener = (bool) ($link->noopener ?? false);
            $noreferrer = (bool) ($link->noreferrer ?? false);

            // ✅ Check if raw_rel_attr is available (from raw anchor mode)
            $rawRelAttr = trim((string)($link->raw_rel_attr ?? ''));

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

                // ✅ UTF-8 Sanitization - clean malformed bytes
                $keyword = cleanUtf8((string) $keyword, [
                    'context' => 'sidebar_blogroll_api',
                    'task_id' => $task->id,
                    'field' => 'keyword',
                ]);

                $targetUrl = cleanUtf8((string) $targetUrl, [
                    'context' => 'sidebar_blogroll_api',
                    'task_id' => $task->id,
                    'field' => 'link',
                ]);

                $payload = [
                    'keyword' => $keyword,
                    'link'    => $targetUrl,
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
                \Log::info('Sidebar Blogroll Payload Debug', [
                    'task_id' => $task->id,
                    'domain' => $domain->name,
                    'rel_array' => $rel,
                    'rel_string' => $relString,
                    'full_payload' => $payload,
                ]);

                // 🌐 Send request with UTF-8 safe headers
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

                $remoteIds[] = $remoteId;
                $responses[] = $json;
            }

            // ✅ Store first remote ID (or JSON array of all IDs)
            $finalRemoteId = count($remoteIds) === 1 ? $remoteIds[0] : json_encode($remoteIds);
            $finalResponse = count($responses) === 1 ? $responses[0] : ['multiple' => $responses];

            // ✅ STEP 4: Mark success
            DB::transaction(function () use ($task, $finalResponse, $finalRemoteId) {

                $fresh = SidebarCampaignTask::lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->lock_token !== $task->lock_token) return;

                $fresh->status         = 'success';
                $fresh->remote_id      = $finalRemoteId;
                $fresh->http_status    = 200;
                $fresh->remote_response = $finalResponse;
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
