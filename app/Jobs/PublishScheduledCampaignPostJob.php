<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\Article;
use Illuminate\Support\Facades\Log;

class PublishScheduledCampaignPostJob implements ShouldQueue
{
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public int $tries = 5;

        public function __construct(public int $postId)
        {
            $this->onQueue('scheduled_campaigns');
        }

        public function handle(): void
        {
            $lockTtlSec  = 300; // 5 minutes
            $maxAttempts = 5;
            $baseBackoff = 60;

            $lockToken = (string) Str::uuid();

            // 🔐 STEP 1: Claim post safely
            $post = DB::transaction(function () use ($lockToken, $lockTtlSec) {

                $p = ScheduleCampaignPost::query()
                    ->with('campaign')
                    ->lockForUpdate()
                    ->find($this->postId);

                if (!$p) return null;

                if (in_array($p->status, ['success', 'failed'], true)) return null;
                if ($p->campaign && in_array($p->campaign->status, ['paused', 'cancelled'], true)) return null;

                if ($p->next_retry_at && $p->next_retry_at->isFuture()) return null;

                // if ($p->schedule_at && $p->schedule_at->isFuture()) {
                //     return null;
                // }


                if ($p->locked_at && $p->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                    return null;
                }


                $p->status     = 'publishing';
                $p->locked_at  = now();
                $p->lock_token = $lockToken;
                $p->save();


                // ✅ ADD HERE (EXACT PLACE)
                ScheduleCampaign::whereKey($p->schedule_campaign_id)
                    ->whereNull('started_at')
                    ->update(['started_at' => now()]);

                return $p;
            });

            if (!$post) return;

            // Load everything required
            $post->load([
                'campaign',
                'campaignDomain.domain',
                'campaignArticle.article',
            ]);

            try {
                // 🧠 STEP 2: Build title + content WITH KEYWORDS
                [$title, $content] = $this->buildContent($post);

                // 🌐 STEP 3: Publish to WordPress
                $remote = $this->postToWordPress($post, $title, $content);

                // ✅ STEP 4: Mark success or fail with clear reason
                DB::transaction(function () use ($post, $remote) {

                    $fresh = ScheduleCampaignPost::lockForUpdate()->find($post->id);
                    if (!$fresh || $fresh->lock_token !== $post->lock_token) return;

                    $json = $remote['json'];
                    $remoteStatus = $json['status'] ?? null;
                    // Post created on remote is success whether published now or scheduled (future)
                    $isSuccess = in_array($remoteStatus, ['publish', 'future'], true);

                    $fresh->status       = $isSuccess ? 'success' : 'failed';
                    $fresh->remote_id    = $json['post_id'] ?? null;
                    $fresh->remote_status = $remoteStatus;
                    $fresh->http_status  = $remote['http_status'];
                    $fresh->remote_title = $post->campaignArticle->article->name ?? null;
                    // No schedule in payload → API returns slug permalink
                    $fresh->remote_url = $json['remote_url'] ?? $json['link'] ?? $json['permalink'] ?? $json['url'] ?? null;

                    $fresh->remote_response = json_encode($json, JSON_UNESCAPED_UNICODE);
                    $fresh->published_at    = ($fresh->remote_status === 'publish') ? now() : null;

                    // When we mark failed (e.g. remote returned draft/other), store reason so UI shows it
                    $fresh->last_error    = $isSuccess ? null : (
                        'Remote post status was: "' . ($remoteStatus ?? 'unknown') . '" (expected publish or future).'
                    );
                    $fresh->next_retry_at = null;
                    $fresh->locked_at     = null;
                    $fresh->lock_token    = null;

                    $fresh->save();

                    if ($fresh->status === 'success') {
                        ScheduleCampaign::whereKey($fresh->schedule_campaign_id)
                            ->increment('completed_targets');
                    } else {
                        ScheduleCampaign::whereKey($fresh->schedule_campaign_id)
                            ->increment('failed_targets');
                    }

                    // Article::whereKey($fresh->campaignArticle->article_id)
                    //     ->update(['status' => 1]);
                    Article::find($fresh->campaignArticle->article_id)?->delete();
                });
            } catch (Throwable $e) {

                // ❌ STEP 5: Retry / fail
                DB::transaction(function () use ($post, $e, $maxAttempts, $baseBackoff) {

                    $fresh = ScheduleCampaignPost::lockForUpdate()->find($post->id);
                    if (!$fresh || $fresh->lock_token !== $post->lock_token) return;

                    $fresh->attempt_count++;
                    $fresh->last_error = $e->getMessage();

                    if ($fresh->attempt_count < $maxAttempts) {

                        $delay = min(
                            (int) ($baseBackoff * (2 ** ($fresh->attempt_count - 1))),
                            3600
                        );

                        $fresh->status = 'queued';
                        $fresh->next_retry_at = now()->addSeconds($delay);
                        $fresh->locked_at = null;
                        $fresh->lock_token = null;
                        $fresh->save();

                        PublishScheduledCampaignPostJob::dispatch($fresh->id)
                            ->onQueue('scheduled_campaigns')
                            ->delay(now()->addSeconds($delay));
                    } else {

                        $fresh->status = 'failed';
                        $fresh->http_status = $e->getCode() ?: null;
                        $fresh->next_retry_at = null;
                        $fresh->locked_at = null;
                        $fresh->lock_token = null;
                        $fresh->save();

                        ScheduleCampaign::whereKey($fresh->schedule_campaign_id)
                            ->increment('failed_targets');
                    }
                });
            } finally {
                $this->finalizeCampaignIfDone($post->schedule_campaign_id);
            }
        }

        // 🔥 IMPORTANT:
        // buildContent(), findSafeHtmlInsertPos(), postToWordPress()
        // 👉 COPY THEM **AS-IS** from your existing job



        private function buildContent(ScheduleCampaignPost $post): array
        {
            $article = $post->campaignArticle->article;

            if (!$article) {
                throw new \Exception("Article not found. campaign_post_id={$post->id}");
            }

            $title = trim((string) $article->name);
            $html  = trim((string) $article->description);

            if ($title === '' || $html === '') {
                throw new \Exception("Article missing content");
            }

            // --------------------------------------------------
            // 1) Collect keyword + url pairs (SEQUENTIAL)
            // --------------------------------------------------
            $ca = $post->campaignArticle;

            $keywords = $ca->keyword_type === 'json'
                ? json_decode($ca->keyword, true)
                : [$ca->keyword];

            $urls = $ca->url_type === 'json'
                ? json_decode($ca->url, true)
                : [$ca->url];

            if (!is_array($keywords) || !is_array($urls)) {
                throw new \Exception("Invalid keyword/url format");
            }

            // normalize + pair sequentially
            $pairs = [];
            $max = min(count($keywords), count($urls));

            for ($i = 0; $i < $max; $i++) {
                $kw  = trim((string) ($keywords[$i] ?? ''));
                $url = trim((string) ($urls[$i] ?? ''));

                if ($kw !== '' && $url !== '') {
                    $pairs[] = [$kw, $url];
                }
            }

            if (count($pairs) === 0) {
                throw new \Exception("No valid keyword/url pairs");
            }

            // --------------------------------------------------
            // 2) Split content into paragraphs
            // --------------------------------------------------
            preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
            $paragraphs = $matches[0] ?? [];

            // Fallback: no <p> tags → wrap entire content
            if (count($paragraphs) === 0) {
                $paragraphs = ['<p>' . $html . '</p>'];
            }

            $paraCount   = count($paragraphs);
            $anchorCount = count($pairs);

            // --------------------------------------------------
            // 3) Distribute anchors across paragraphs
            // Example: 5 anchors, 3 paras → 2 | 2 | 1
            // --------------------------------------------------
            // ** OLD ONES SHUFFLE CODE HERE **
            // --------------------------------------------------

      
            // --------------------------------------------------
            // ** ENDS HERE **
            // --------------------------------------------------


            shuffle($pairs);                    // randomize anchors
            $paraIndexes = array_keys($paragraphs);
            shuffle($paraIndexes);              // randomize paragraph order

            $pairIndex = 0;
            $nofollow  = (bool) ($ca->nofollow ?? false);
            $relAttr   = $nofollow ? 'nofollow noopener' : 'noopener';

            while ($pairIndex < $anchorCount) {

                foreach ($paraIndexes as $p) {
                    if ($pairIndex >= $anchorCount) break;

                    $paraHtml = $paragraphs[$p];

                    // keep original <p> tag
                    preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
                    $openTag = $openTagMatch[0] ?? '<p>';

                    // extract inner HTML
                    $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);

                    // skip very short paragraphs
                    if (mb_strlen(trim(strip_tags($inner))) < 30) {
                        continue;
                    }

                    [$kw, $url] = $pairs[$pairIndex++];

                    $anchor = '<a href="' . e($url) . '" target="_blank" rel="' . $relAttr . '">' . e($kw) . '</a>';

                    // random safe insertion point (25%–65%)
                    $target = random_int(
                        (int) (strlen($inner) * 0.1),
                        (int) (strlen($inner) * 0.3)
                    );

                    $safePos = $this->findSafeHtmlInsertPos($inner, $target);

                    $inner = substr($inner, 0, $safePos)
                        . ' ' . $anchor . ' '
                        . substr($inner, $safePos);

                    $paragraphs[$p] = $openTag . $inner . '</p>';
                }
            }

            // --------------------------------------------------
            // 4) Rebuild HTML
            // --------------------------------------------------
            $html = implode("\n", $paragraphs);

            return [$title, $html];
        }

     
        // ** new one */

        private function findSafeHtmlInsertPos(string $html, int $start): int
        {
            $len = strlen($html);
            if ($len === 0) return 0;

            $start = max(0, min($start, $len));

            $isBoundary = function (string $ch): bool {
                return in_array($ch, [
                    ' ',
                    "\n",
                    "\t",
                    '.',
                    ',',
                    ';',
                    ':',
                    '!',
                    '?',
                    ')',
                    '('
                ], true);
            };

            $insideTagAt = function (int $pos) use ($html): bool {
                $before = substr($html, 0, $pos);
                $lastLt = strrpos($before, '<');
                if ($lastLt === false) return false;

                $lastGt = strrpos($before, '>');
                return $lastGt === false || $lastLt > $lastGt;
            };

            for ($d = 0; $d < 200; $d++) {

                $right = $start + $d;
                if ($right < $len && !$insideTagAt($right)) {
                    $ch = substr($html, $right, 1); // ✅ UTF-8 SAFE
                    if ($ch !== '' && $isBoundary($ch)) {
                        return min($right + 1, $len);
                    }
                }

                $left = $start - $d;
                if ($left > 0 && !$insideTagAt($left)) {
                    $ch = substr($html, $left, 1); // ✅ UTF-8 SAFE
                    if ($ch !== '' && $isBoundary($ch)) {
                        return min($left + 1, $len);
                    }
                }
            }

            // fallback
            $pos = min($start, $len);
            while ($pos < $len && $insideTagAt($pos)) {
                $pos++;
            }

            return min($pos, $len);
        }


        private function postToWordPress(ScheduleCampaignPost $post, string $title, string $content): array
        {

            Log::info('🚀 postToWordPress() ENTERED', [
                'post_id' => $post->id,
                'domain'  => $post->campaignDomain->domain->name,
            ]);


            $domain = trim((string) $post->campaignDomain->domain->name);

            // ✅ Ensure scheme
            if (!preg_match('~^https?://~i', $domain)) {
                $domain = 'https://' . $domain;
            }

            $endpoint = rtrim($domain, '/') . '/wp-json/external/v1/posts/create';

            // Our scheduler runs the job by schedule_at; when job runs we publish immediately (no WP scheduling).
            $payload = [
                'title'     => $title,
                'content'   => $content,
                'status'    => 'publish',
                'post_type' => 'post',
                'api_key'   => (string) $post->campaignDomain->domain->api_key,
            ];
            // Log::info('Calling WordPress API', [
            //     'endpoint' => $endpoint,
            //     'payload' => $payload,
            // ]);

            $res = Http::withoutVerifying() // keep if you must for bad SSL domains
                ->timeout(180)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if (!$res->successful()) {
                throw new \Exception("WP API failed ({$res->status()}): " . $res->body());
            }

            $json = $res->json();
            if (!is_array($json)) {
                throw new \Exception("WP API returned non-JSON response: " . $res->body());
            }

            // return $json;

            return [
                'json'        => $json,
                'http_status' => $res->status(),
            ];
        }

        /**
         * 🏁 Finalize campaign if all posts processed
         */
        private function finalizeCampaignIfDone(int $campaignId): void
        {
            $campaign = ScheduleCampaign::find($campaignId);
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
