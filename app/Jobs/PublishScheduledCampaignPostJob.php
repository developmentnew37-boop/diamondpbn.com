<?php

namespace App\Jobs;

use App\Models\Admin\Article;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleCampaignPost;
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

class PublishScheduledCampaignPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $postId, public int $dispatchGeneration = 0)
    {
        $this->onQueue('scheduled_campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec = config('campaign.jobs.lock_ttl_seconds');
        $maxAttempts = config('campaign.jobs.max_internal_retries');
        $baseBackoff = config('campaign.jobs.base_backoff_seconds');

        $lockToken = (string) Str::uuid();

        // 🔐 STEP 1: Claim post safely
        $post = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $p = ScheduleCampaignPost::query()
                ->with('campaign')
                ->lockForUpdate()
                ->find($this->postId);

            if (! $p) {
                return null;
            }

            if ((int) $p->dispatch_generation !== $this->dispatchGeneration) {
                return null;
            }

            if ($p->is_converted_live) {
                return null;
            }

            if (in_array($p->status, ['success', 'failed'], true)) {
                return null;
            }
            if ($p->campaign && in_array($p->campaign->status, ['paused', 'cancelled'], true)) {
                return null;
            }

            if ($p->next_retry_at && $p->next_retry_at->isFuture()) {
                return null;
            }

            // if ($p->schedule_at && $p->schedule_at->isFuture()) {
            //     return null;
            // }

            if ($p->locked_at && $p->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                return null;
            }

            $p->status = 'publishing';
            $p->locked_at = now();
            $p->lock_token = $lockToken;
            $p->save();

            // ✅ ADD HERE (EXACT PLACE)
            ScheduleCampaign::whereKey($p->schedule_campaign_id)
                ->whereNull('started_at')
                ->update(['started_at' => now()]);

            return $p;
        });

        if (! $post) {
            return;
        }

        // Load everything required
        $post->load([
            'campaign',
            'campaignDomain.domain',
            'campaignArticle.article',
        ]);

        try {
            // 🧠 STEP 2: Build title + content WITH KEYWORDS
            [$title, $content] = $this->buildContent($post);

            // ✅ UTF-8 Sanitization - clean malformed bytes before WordPress API posting
            $title = cleanUtf8($title, [
                'context' => 'wp_api_post',
                'article_id' => $post->campaignArticle?->article_id,
                'post_id' => $post->id,
                'field' => 'title',
                'language' => $post->campaignArticle?->article?->language?->name,
            ]);

            $content = cleanUtf8($content, [
                'context' => 'wp_api_post',
                'article_id' => $post->campaignArticle?->article_id,
                'post_id' => $post->id,
                'field' => 'content',
                'language' => $post->campaignArticle?->article?->language?->name,
            ]);

            // 🌐 STEP 3: Publish to WordPress
            $remote = $this->postToWordPress($post, $title, $content);

            // ✅ STEP 4: Mark success or fail with clear reason
            DB::transaction(function () use ($post, $remote, $title) {

                $fresh = ScheduleCampaignPost::lockForUpdate()->find($post->id);
                if (! $fresh || $fresh->lock_token !== $post->lock_token) {
                    return;
                }

                $json = $remote['json'];
                $remoteStatus = $json['status'] ?? null;
                // Post created on remote is success whether published now or scheduled (future)
                $isSuccess = in_array($remoteStatus, ['publish', 'future'], true);

                $fresh->status = $isSuccess ? 'success' : 'failed';
                $fresh->remote_id = $json['post_id'] ?? null;
                $fresh->remote_status = $remoteStatus;
                $fresh->http_status = $remote['http_status'];
                $fresh->remote_title = $title;
                // No schedule in payload → API returns slug permalink
                $fresh->remote_url = $json['remote_url'] ?? $json['link'] ?? $json['permalink'] ?? $json['url'] ?? null;

                $fresh->remote_response = safeJsonEncode($json);
                $fresh->published_at = ($fresh->remote_status === 'publish') ? now() : null;

                // When we mark failed (e.g. remote returned draft/other), store reason so UI shows it
                $fresh->last_error = $isSuccess ? null : (
                    'Remote post status was: "'.($remoteStatus ?? 'unknown').'" (expected publish or future).'
                );
                $fresh->next_retry_at = null;
                $fresh->locked_at = null;
                $fresh->lock_token = null;

                $fresh->save();

                if ($fresh->status === 'success') {
                    ScheduleCampaign::whereKey($fresh->schedule_campaign_id)
                        ->increment('completed_targets');
                } else {
                    ScheduleCampaign::whereKey($fresh->schedule_campaign_id)
                        ->increment('failed_targets');
                }

                if ($fresh->status === 'success') {
                    $ca = ScheduleCampaignArticle::lockForUpdate()->find($fresh->schedule_campaign_article_id);
                    if ($ca && $ca->article_id) {
                        $art = Article::find($ca->article_id);
                        if ($art) {
                            $ca->update([
                                'article_title_snapshot' => $art->name,
                                'article_body_snapshot' => $art->description,
                            ]);
                            $art->delete();
                        }
                    }
                }
            });
        } catch (Throwable $e) {

            // ❌ STEP 5: Retry / fail
            DB::transaction(function () use ($post, $e, $maxAttempts, $baseBackoff) {

                $fresh = ScheduleCampaignPost::lockForUpdate()->find($post->id);
                if (! $fresh || $fresh->lock_token !== $post->lock_token) {
                    return;
                }

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

                    PublishScheduledCampaignPostJob::dispatch($fresh->id, (int) $fresh->dispatch_generation)
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
        $ca = $post->campaignArticle;
        if (! $ca) {
            throw new \Exception("Campaign article not found. campaign_post_id={$post->id}");
        }

        $article = $ca->article;
        if ($article) {
            $title = trim((string) $article->name);
            $html = trim((string) $article->description);
        } else {
            $title = trim((string) ($ca->article_title_snapshot ?? ''));
            $html = trim((string) ($ca->article_body_snapshot ?? ''));
        }

        if ($title === '' || $html === '') {
            throw new \Exception(
                "Article content missing for campaign_post_id={$post->id} (library article removed; snapshots required)."
            );
        }

        // --------------------------------------------------
        // 1) Collect keyword + url pairs (SEQUENTIAL)
        // --------------------------------------------------

        $keywords = $ca->keyword_type === 'json'
            ? json_decode($ca->keyword, true)
            : [$ca->keyword];

        $urls = $ca->url_type === 'json'
            ? json_decode($ca->url, true)
            : [$ca->url];

        if (! is_array($keywords) || ! is_array($urls)) {
            throw new \Exception('Invalid keyword/url format');
        }

        // normalize + pair sequentially
        $pairs = [];
        $max = min(count($keywords), count($urls));

        for ($i = 0; $i < $max; $i++) {
            $kw = trim((string) ($keywords[$i] ?? ''));
            $url = trim((string) ($urls[$i] ?? ''));

            if ($kw !== '' && $url !== '') {
                $pairs[] = [$kw, $url];
            }
        }

        if (count($pairs) === 0) {
            throw new \Exception('No valid keyword/url pairs');
        }

        // --------------------------------------------------
        // 2) Split content into paragraphs
        // --------------------------------------------------
        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
        $paragraphs = $matches[0] ?? [];

        // Check if extracted paragraphs have meaningful content
        $hasMeaningfulContent = false;
        foreach ($paragraphs as $p) {
            $stripped = trim(strip_tags($p));
            if (mb_strlen($stripped) > 10) { // At least 10 chars of actual text
                $hasMeaningfulContent = true;
                break;
            }
        }

        // Fallback: no <p> tags OR only empty <p> tags → split by <br> tags
        if (count($paragraphs) === 0 || ! $hasMeaningfulContent) {
            // Split by <br> tags (br, BR, br/, etc.)
            $parts = preg_split('/<br\s*\/?>/i', $html);
            $paragraphs = [];
            foreach ($parts as $part) {
                $part = trim($part);
                // Remove any empty <p></p> tags that might be in the part
                $part = preg_replace('/<p\b[^>]*>\s*<\/p>/i', '', $part);
                $part = trim($part);
                if ($part !== '') {
                    $paragraphs[] = '<p>'.$part.'</p>';
                }
            }

            // Still no content? Wrap entire HTML as single paragraph
            if (count($paragraphs) === 0) {
                $paragraphs = ['<p>'.$html.'</p>'];
            }
        }

        $paraCount = count($paragraphs);
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

        // ✅ PRIORITY 1: Use raw_rel_attr if present (from Raw HTML Anchors mode - supports ANY rel values)
        // ✅ PRIORITY 2: Build from individual boolean fields (from checkbox mode - backward compatibility)
        $relPart = '';
        if (! empty($ca->raw_rel_attr)) {
            // Raw HTML mode: Use full rel string directly (supports custom values like "external", "bookmark")
            $relPart = ' rel="'.e(trim($ca->raw_rel_attr)).'"';
        } else {
            // Checkbox mode: Build from boolean fields (legacy behavior)
            $nofollow = (bool) ($ca->nofollow ?? false);
            $sponsored = (bool) ($ca->sponsored ?? false);
            $ugc = (bool) ($ca->ugc ?? false);
            $noopener = (bool) ($ca->noopener ?? false);
            $noreferrer = (bool) ($ca->noreferrer ?? false);

            $relTokens = [];
            if ($nofollow) {
                $relTokens[] = 'nofollow';
            }
            if ($sponsored) {
                $relTokens[] = 'sponsored';
            }
            if ($ugc) {
                $relTokens[] = 'ugc';
            }
            if ($noopener) {
                $relTokens[] = 'noopener';
            }
            if ($noreferrer) {
                $relTokens[] = 'noreferrer';
            }
            $relPart = count($relTokens) > 0 ? ' rel="'.implode(' ', $relTokens).'"' : '';
        }

        // ✅ FIX: Prevent infinite loop by tracking passes
        $minLength = 30; // Preferred minimum paragraph length
        $maxPasses = 2;  // Pass 1: >= 30 chars, Pass 2: any length
        $currentPass = 0;

        while ($pairIndex < $anchorCount && $currentPass < $maxPasses) {
            $placedThisPass = false;

            foreach ($paraIndexes as $p) {
                if ($pairIndex >= $anchorCount) {
                    break;
                }

                $paraHtml = $paragraphs[$p];

                // keep original <p> tag
                preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
                $openTag = $openTagMatch[0] ?? '<p>';

                // extract inner HTML
                $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);

                $textLen = mb_strlen(trim(strip_tags($inner)));

                // Pass 1: skip short paragraphs; Pass 2: accept any paragraph with text
                if ($currentPass === 0 && $textLen < $minLength) {
                    continue;
                }
                if ($textLen < 1) {
                    continue; // Always skip completely empty paragraphs
                }

                [$kw, $url] = $pairs[$pairIndex++];
                $placedThisPass = true;

                $anchor = '<a href="'.e($url).'" target="_blank"'.$relPart.'>'.e($kw).'</a>';

                // random safe insertion point (10%–30%)
                // ✅ Use mb_strlen for character count, not byte count (critical for Chinese/Thai/Arabic)
                $target = random_int(
                    (int) (mb_strlen($inner) * 0.1),
                    (int) (mb_strlen($inner) * 0.3)
                );

                $safePos = $this->findSafeHtmlInsertPos($inner, $target);

                // ✅ Use mb_substr to avoid splitting multi-byte UTF-8 characters
                $inner = mb_substr($inner, 0, $safePos)
                    .' '.$anchor.' '
                    .mb_substr($inner, $safePos);

                $paragraphs[$p] = $openTag.$inner.'</p>';
            }

            // ✅ If no anchors were placed this pass, try next pass with relaxed rules
            if (! $placedThisPass) {
                $currentPass++;
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
        // ✅ Use mb_strlen for character-based length (critical for Chinese/Thai/Arabic)
        $len = mb_strlen($html);
        if ($len === 0) {
            return 0;
        }

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
                '(',
            ], true);
        };

        $insideTagAt = function (int $pos) use ($html): bool {
            $before = mb_substr($html, 0, $pos);
            $lastLt = mb_strrpos($before, '<');
            if ($lastLt === false) {
                return false;
            }

            $lastGt = mb_strrpos($before, '>');

            return $lastGt === false || $lastLt > $lastGt;
        };

        for ($d = 0; $d < 200; $d++) {

            $right = $start + $d;
            if ($right < $len && ! $insideTagAt($right)) {
                $ch = mb_substr($html, $right, 1); // ✅ UTF-8 SAFE
                if ($ch !== '' && $isBoundary($ch)) {
                    return min($right + 1, $len);
                }
            }

            $left = $start - $d;
            if ($left > 0 && ! $insideTagAt($left)) {
                $ch = mb_substr($html, $left, 1); // ✅ UTF-8 SAFE
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
            'domain' => $post->campaignDomain->domain->name,
        ]);

        $domain = trim((string) $post->campaignDomain->domain->name);

        // ✅ Ensure scheme
        if (! preg_match('~^https?://~i', $domain)) {
            $domain = 'https://'.$domain;
        }

        $endpoint = rtrim($domain, '/').'/wp-json/external/v1/posts/create';

        // Our scheduler runs the job by schedule_at; when job runs we publish immediately (no WP scheduling).
        $payload = [
            'title' => $title,
            'content' => $content,
            'status' => 'publish',
            'post_type' => 'post',
            'api_key' => (string) $post->campaignDomain->domain->api_key,
            'is_sticky' => (bool) ($post->campaign?->is_sticky_campaign ?? false),
        ];

        // ✅ UTF-8 Safe: Use proper headers and ensure payload is clean
        $res = Http::withoutVerifying() // keep if you must for bad SSL domains
            ->timeout(180)
            ->acceptJson()
            ->contentType('application/json; charset=utf-8')
            ->withBody(safeJsonEncode($payload), 'application/json; charset=utf-8')
            ->post($endpoint);

        if (! $res->successful()) {
            throw new \Exception("WP API failed ({$res->status()}): ".$res->body());
        }

        $json = $res->json();
        if (! is_array($json)) {
            throw new \Exception('WP API returned non-JSON response: '.$res->body());
        }

        // return $json;

        return [
            'json' => $json,
            'http_status' => $res->status(),
        ];
    }

    /**
     * 🏁 Finalize campaign if all posts processed
     */
    private function finalizeCampaignIfDone(int $campaignId): void
    {
        $campaign = ScheduleCampaign::find($campaignId);
        if (! $campaign) {
            return;
        }

        $totalDone = $campaign->completed_targets + $campaign->failed_targets;

        if ($totalDone < $campaign->total_targets) {
            return;
        }

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
