<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Services\HiddenLinksApiService;

use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Models\Admin\HiddenLinksCampaignLinks;

class PublishHiddenLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $taskId)
    {
        $this->onQueue('hidden_links_campaigns');
    }

    /** Backoff in seconds when the job throws (e.g. DB/connection errors). */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('PublishHiddenLinksJob failed', [
            'task_id' => $this->taskId,
            'message' => $e ? $e->getMessage() : 'unknown',
        ]);
    }

    public function handle(): void
    {
        $lockTtlSec  = 180; // 3 minutes
        $maxAttempts = 5;
        $baseBackoff = 60;  // seconds (1m, 2m, 4m, 8m...)

        $lockToken = (string) Str::uuid();

        // 🔐 STEP 1: Claim task safely (same pattern as sidebar)
        $task = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $t = HiddenLinksCampaignTasks::query()
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

        // Load relations needed (make sure these relations exist in your models)
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

            // ✅ Hidden Links endpoint
            $endpoint = rtrim($base, '/') . '/wp-json/external/v1/hidden-links/add';
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
                'context' => 'hidden_links_api',
                'task_id' => $task->id,
                'field' => 'keyword',
            ]);

            $targetUrl = cleanUtf8((string) $link->target_url, [
                'context' => 'hidden_links_api',
                'task_id' => $task->id,
                'field' => 'link',
            ]);

            // ✅ Payload you gave
            $payload = [
                'keyword' => $keyword,
                'link'    => $targetUrl,
                'api_key' => (string) $apiKey,
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
                throw new \Exception("WP hidden-links API failed ({$res->status()}): " . $res->body());
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

            // Some remote implementations ignore rel on ADD.
            // Force rel persistence via UPDATE when we have a remote id.
            if ($remoteId && count($rel) > 0) {
                $syncRes = HiddenLinksApiService::updateEntry(
                    $domain->name,
                    (string) $apiKey,
                    (string) $remoteId,
                    (string) $link->anchor_keyword,
                    (string) $link->target_url,
                    $rel
                );
                if (!$syncRes->successful()) {
                    throw new \Exception("WP hidden-links rel sync failed ({$syncRes->status()}): " . $syncRes->body());
                }
            }

            // ✅ STEP 4: Mark success
            DB::transaction(function () use ($task, $json, $res,$remoteId) {

                $fresh = HiddenLinksCampaignTasks::lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->lock_token !== $task->lock_token) return;

                $fresh->status          = 'success';
                 $fresh->remote_id      = $remoteId;
                $fresh->http_status     = $res->status();
                $fresh->remote_response = $json; // if column is JSON, ok. If longtext, keep json_encode.
                $fresh->published_at    = now();
                $fresh->finished_at     = now();
                $fresh->last_error      = null;
                $fresh->next_retry_at   = null;
                $fresh->locked_at       = null;
                $fresh->lock_token      = null;
                $fresh->save();

                HiddenLinksCampaign::whereKey($fresh->hidden_links_campaigns_id)
                    ->increment('completed_targets');
            });
        } catch (Throwable $e) {

            // ❌ STEP 5: Retry or fail (same as sidebar)
            DB::transaction(function () use ($task, $e, $maxAttempts, $baseBackoff) {

                $fresh = HiddenLinksCampaignTasks::lockForUpdate()->find($task->id);
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
                    PublishHiddenLinksJob::dispatch($fresh->id)
                        ->onQueue('hidden_links_campaigns')
                        ->delay(now()->addSeconds($delay));
                } else {

                    $fresh->status = 'failed';
                    $fresh->next_retry_at = null;
                    $fresh->locked_at  = null;
                    $fresh->lock_token = null;
                    $fresh->finished_at = now();
                    $fresh->save();

                    HiddenLinksCampaign::whereKey($fresh->hidden_links_campaigns_id)
                        ->increment('failed_targets');
                }
            });
        } finally {
            $this->finalizeHiddenLinksCampaignIfDone($task->hidden_links_campaigns_id);
        }
    }

    private function finalizeHiddenLinksCampaignIfDone(int $campaignId): void
    {
        $campaign = HiddenLinksCampaign::find($campaignId);
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
