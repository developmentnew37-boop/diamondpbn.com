<?php

namespace App\Jobs;

use App\Models\Admin\Article;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\CampaignPost;
use App\Services\CampaignPostContentBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PublishCampaignPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $dispatchGeneration = 0;

    public function __construct(
        public int $campaignPostId,
        int $dispatchGeneration = 0,
    ) {
        $this->dispatchGeneration = $dispatchGeneration;
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        $lockTtlSec = config('campaign.jobs.lock_ttl_seconds');
        $maxAttempts = config('campaign.jobs.max_internal_retries');
        $baseBackoff = config('campaign.jobs.base_backoff_seconds');

        $lockToken = (string) Str::uuid();

        // 🔐 STEP 1: Claim the campaign_post safely
        $post = DB::transaction(function () use ($lockToken, $lockTtlSec) {

            $p = CampaignPost::query()
                ->with('campaign')
                ->lockForUpdate()
                ->find($this->campaignPostId);

            if (! $p) {
                return null;
            }

            if ($p->dispatch_generation !== $this->dispatchGeneration) {
                return null;
            }
            if (in_array($p->status, ['success', 'failed'], true)) {
                return null;
            }
            if (in_array($p->campaign->status, ['paused', 'cancelled'], true)) {
                return null;
            }

            if ($p->next_retry_at && $p->next_retry_at->isFuture()) {
                return null;
            }

            if ($p->locked_at && $p->locked_at->gt(now()->subSeconds($lockTtlSec))) {
                return null;
            }

            $p->status = 'publishing';
            $p->locked_at = now();
            $p->lock_token = $lockToken;
            $p->save();

            return $p;
        });

        if (! $post) {
            return;
        }

        // Load everything needed
        $post->load([
            'campaign',
            'campaignDomain.domain',
            'campaignArticle.article',
        ]);

        $finalizeCampaign = false;

        try {
            // 🧠 STEP 2: Build content
            [$title, $content] = CampaignPostContentBuilder::build($post);

            // ✅ UTF-8 Sanitization - clean malformed bytes before WordPress API posting
            $title = cleanUtf8($title, [
                'context' => 'campaign_api_post',
                'article_id' => $post->campaignArticle?->article_id,
                'post_id' => $post->id,
                'field' => 'title',
                'language' => $post->campaignArticle?->article?->language?->name,
            ]);

            $content = cleanUtf8($content, [
                'context' => 'campaign_api_post',
                'article_id' => $post->campaignArticle?->article_id,
                'post_id' => $post->id,
                'field' => 'content',
                'language' => $post->campaignArticle?->article?->language?->name,
            ]);

            // 🔍 DEBUG: Log what HTML we're sending to WordPress
            \Log::info('📤 SENDING TO WORDPRESS', [
                'campaign_post_id' => $post->id,
                'campaign_article_id' => $post->campaign_article_id,
                'raw_rel_attr' => $post->campaignArticle?->raw_rel_attr,
                'title' => $title,
                'content_length' => strlen($content),
                'content_first_500' => substr($content, 0, 500),
                'anchor_tags_count' => substr_count($content, '<a '),
            ]);

            // 🌐 STEP 3: Send to WordPress
            if (! $this->markRemoteAttemptStarted($post)) {
                return;
            }

            $remote = $this->postToWordPress($post, $title, $content);

            // 🔍 DEBUG: Log what WordPress returned
            \Log::info('📥 WORDPRESS RESPONSE', [
                'campaign_post_id' => $post->id,
                'remote_post_id' => $remote['post_id'] ?? null,
                'remote_url' => $remote['remote_url'] ?? null,
                'http_status' => $remote['status'] ?? 'unknown',
                'response_keys' => array_keys($remote),
            ]);

            // ✅ STEP 4: Mark success
            $finalizeCampaign = DB::transaction(function () use ($post, $remote, $title) {

                // Lock campaign post
                $fresh = CampaignPost::lockForUpdate()->find($post->id);
                if (! $this->stillOwns($fresh, $post)) {
                    return false;
                }

                // Update post status (title matches published payload; safe if article row is later removed)
                $fresh->update([
                    'status' => 'success',
                    'remote_id' => $remote['post_id'] ?? null,
                    'remote_title' => $title,
                    'remote_url' => $remote['remote_url'] ?? null,
                    'published_at' => now(),
                    'last_error' => null,
                    'delivery_state' => CampaignPost::DELIVERY_REMOTE_CREATED,
                    'last_failure_code' => null,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                ]);

                // 🔒 LOCK campaign row FIRST
                $campaign = Campaign::lockForUpdate()->find($fresh->campaign_id);
                if (! $campaign) {
                    return false;
                }

                // ➕ Increment locally
                $campaign->completed_targets++;

                // ✅ Check completion
                if ($campaign->completed_targets >= $campaign->total_targets) {
                    $campaign->status = 'completed';
                }

                if ($campaign->completed_targets + $campaign->failed_targets > $campaign->total_targets) {
                    $campaign->failed_targets--;
                }

                // 💾 Save once
                $campaign->save();

                // Keep copy on campaign_articles, then soft-delete library article (permanent purge won't cascade-delete this row)
                $ca = CampaignArticle::lockForUpdate()->find($fresh->campaign_article_id);
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

                return true;
            });
        } catch (Throwable $e) {
            [$failureCode, $deliveryState] = $this->classifyFailure($e);

            // ❌ STEP 5: Retry or fail
            $finalizeCampaign = DB::transaction(function () use (
                $post,
                $e,
                $failureCode,
                $deliveryState,
                $maxAttempts,
                $baseBackoff
            ) {

                $fresh = CampaignPost::lockForUpdate()->find($post->id);
                if (! $this->stillOwns($fresh, $post)) {
                    return false;
                }

                $fresh->attempt_count++;
                $fresh->last_error = $e->getMessage();
                $fresh->last_failure_code = $failureCode;

                if ($deliveryState !== null) {
                    $fresh->delivery_state = $deliveryState;
                }

                if ($fresh->attempt_count < $maxAttempts) {

                    $fresh->status = 'queued';

                    $delay = (int) ($baseBackoff * (2 ** ($fresh->attempt_count - 1)));
                    $delay = min($delay, 3600);

                    $fresh->next_retry_at = now()->addSeconds($delay);
                    $fresh->locked_at = null;
                    $fresh->lock_token = null;
                    $fresh->save();

                    // ✅ IMPORTANT: re-dispatch job with delay
                    PublishCampaignPostJob::dispatch($fresh->id, $fresh->dispatch_generation)
                        ->onQueue('campaigns')
                        ->delay(now()->addSeconds($delay));

                    return false;
                } else {
                    $fresh->status = 'failed';
                    $fresh->next_retry_at = null;
                    $fresh->locked_at = null;
                    $fresh->lock_token = null;
                    $fresh->save();
                    // first mark post as failed, then increment failed_targets in campaign
                    $campaign = Campaign::lockForUpdate()->find($fresh->campaign_id);

                    if (! $campaign) {
                        return false;
                    }

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

                    return true;
                }
            });
        } finally {
            if ($finalizeCampaign) {
                $this->finalizeCampaignIfDone($post->campaign_id);
            }
        }
    }

    // /**
    //  * 🔗 Build title & content with STRICT sequential keyword+url
    //  */
    // private function buildContent(CampaignPost $post): array
    // {
    //     $article = $post->campaignArticle->article;

    //     if (!$article) {
    //         throw new \Exception("Article not found. campaign_post_id={$post->id}");
    //     }

    //     $title = trim((string) $article->name);
    //     $html  = trim((string) $article->description);

    //     if ($title === '' || $html === '') {
    //         throw new \Exception("Article missing content");
    //     }

    //     // --------------------------------------------------
    //     // 1) Collect keyword + url pairs (SEQUENTIAL)
    //     // --------------------------------------------------
    //     $ca = $post->campaignArticle;

    //     $keywords = $ca->keyword_type === 'json'
    //         ? json_decode($ca->keyword, true)
    //         : [$ca->keyword];

    //     $urls = $ca->url_type === 'json'
    //         ? json_decode($ca->url, true)
    //         : [$ca->url];

    //     if (!is_array($keywords) || !is_array($urls)) {
    //         throw new \Exception("Invalid keyword/url format");
    //     }

    //     // normalize + pair sequentially
    //     $pairs = [];
    //     $max = min(count($keywords), count($urls));

    //     for ($i = 0; $i < $max; $i++) {
    //         $kw  = trim((string) $keywords[$i]);
    //         $url = trim((string) $urls[$i]);

    //         if ($kw !== '' && $url !== '') {
    //             $pairs[] = [$kw, $url];
    //         }
    //     }

    //     if (count($pairs) === 0) {
    //         throw new \Exception("No valid keyword/url pairs");
    //     }

    //     // --------------------------------------------------
    //     // 2) Split content into paragraphs
    //     // --------------------------------------------------
    //     preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);

    //     $paragraphs = $matches[0];

    //     // Fallback: no <p> tags → wrap entire content
    //     if (count($paragraphs) === 0) {
    //         $paragraphs = ['<p>' . $html . '</p>'];
    //     }

    //     $paraCount   = count($paragraphs);
    //     $anchorCount = count($pairs);

    //     // --------------------------------------------------
    //     // 3) Distribute anchors across paragraphs
    //     // --------------------------------------------------
    //     // Example: 5 anchors, 3 paras → 2 | 2 | 1
    //     $base      = intdiv($anchorCount, $paraCount);
    //     $remainder = $anchorCount % $paraCount;

    //     $pairIndex = 0;
    //     $nofollow  = (bool) $ca->nofollow;
    //     $relAttr   = $nofollow ? 'nofollow noopener' : 'noopener';

    //     for ($p = 0; $p < $paraCount && $pairIndex < $anchorCount; $p++) {

    //         $insertCount = $base + ($p < $remainder ? 1 : 0);
    //         if ($insertCount <= 0) continue;

    //         $paraHtml = $paragraphs[$p];

    //         // strip <p> wrapper for clean insertion
    //         $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);

    //         // avoid inserting at very beginning
    //         $offset = max(20, (int) (strlen($inner) * 0.4));

    //         for ($k = 0; $k < $insertCount && $pairIndex < $anchorCount; $k++) {

    //             [$kw, $url] = $pairs[$pairIndex++];

    //             $anchor = '<a href="' . e($url) . '" target="_blank" rel="' . $relAttr . '">' . e($kw) . '</a>';

    //             // insert anchor at a natural position
    //             $inner = substr($inner, 0, $offset)
    //                 . ' ' . $anchor . ' '
    //                 . substr($inner, $offset);

    //             // move offset forward for next anchor
    //             $offset += strlen($anchor) + 30;
    //         }

    //         // re-wrap paragraph
    //         $paragraphs[$p] = '<p>' . $inner . '</p>';
    //     }

    //     // --------------------------------------------------
    //     // 4) Rebuild HTML
    //     // --------------------------------------------------
    //     $html = implode("\n", $paragraphs);

    //     return [$title, $html];
    // }

    /**
     * 🌐 Send article to WordPress
     */
    // private function postToWordPress(CampaignPost $post, string $title, string $content): array
    // {
    //     $domain = $post->campaignDomain->domain->name;

    //     $endpoint = rtrim($domain, '/') . '/wp-json/external/v1/posts/create';

    //     $res = Http::withoutVerifying()
    //         ->timeout(180)->post($endpoint, [
    //             'title'     => $title,
    //             'content'   => $content,
    //             'status'    => 'publish',
    //             'post_type' => 'post',
    //             'api_key' => $post->campaignDomain->domain->api_key
    //         ]);

    //     if (!$res->successful()) {
    //         throw new \Exception("WP API failed: " . $res->body());
    //     }

    //     return $res->json();
    // }

    private function buildContent(CampaignPost $post): array
    {
        $article = $post->campaignArticle->article;

        if (! $article) {
            throw new \Exception("Article not found. campaign_post_id={$post->id}");
        }

        $title = trim((string) $article->name);
        $html = trim((string) $article->description);

        if ($title === '' || $html === '') {
            throw new \Exception('Article missing content');
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
        $base = intdiv($anchorCount, $paraCount);
        $remainder = $anchorCount % $paraCount;

        $pairIndex = 0;
        $nofollow = (bool) ($ca->nofollow ?? false);
        $sponsored = (bool) ($ca->sponsored ?? false);
        $relTokens = [];
        if ($nofollow) {
            $relTokens[] = 'nofollow';
        }
        if ($sponsored) {
            $relTokens[] = 'sponsored';
        }
        $relPart = count($relTokens) > 0 ? ' rel="'.implode(' ', $relTokens).'"' : '';

        for ($p = 0; $p < $paraCount && $pairIndex < $anchorCount; $p++) {

            $insertCount = $base + ($p < $remainder ? 1 : 0);
            if ($insertCount <= 0) {
                continue;
            }

            $paraHtml = $paragraphs[$p];

            // Keep the original <p ...> opening tag if present
            preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';

            // Strip <p> wrapper for inner HTML
            $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);

            // If paragraph is too short in real text, skip it (try next paragraphs)
            $plainLen = mb_strlen(trim(strip_tags($inner)));
            // if ($plainLen < 40 && $paraCount > 1) {
            //     // Try next paragraph, DO NOT consume anchors here
            //     continue;
            // }

            // Start around 20% into the paragraph (not at start)
            // ✅ Use mb_strlen for character count, not byte count (critical for Chinese/Thai/Arabic)
            $target = (int) max(20, floor(mb_strlen($inner) * 0.20));

            for ($k = 0; $k < $insertCount && $pairIndex < $anchorCount; $k++) {

                [$kw, $url] = $pairs[$pairIndex++];

                $anchor = '<a href="'.e($url).'" target="_blank"'.$relPart.'>'.e($kw).'</a>';

                // 🔥 Find a SAFE insertion point in HTML (not inside tag, not inside word)
                $safePos = $this->findSafeHtmlInsertPos($inner, $target);

                // ✅ Use mb_substr to avoid splitting multi-byte UTF-8 characters (Chinese, Thai, Arabic)
                $inner = mb_substr($inner, 0, $safePos)
                    .' '.$anchor.' '
                    .mb_substr($inner, $safePos);

                // Move forward for next insertion in the same paragraph
                $target = $safePos + mb_strlen($anchor) + 40;
            }

            $paragraphs[$p] = $openTag.$inner.'</p>';
        }

        // --------------------------------------------------
        // code for Handling short paragraph and Anchor skipping thing
        // --------------------------------------------------

        // 🚑 SAFETY NET — force insert remaining anchors
        if ($pairIndex < $anchorCount) {

            // Use the longest paragraph as fallback
            usort($paragraphs, function ($a, $b) {
                return mb_strlen(strip_tags($b)) <=> mb_strlen(strip_tags($a));
            });

            $fallback = $paragraphs[0];

            preg_match('/^<p\b[^>]*>/i', $fallback, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';

            $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $fallback);

            while ($pairIndex < $anchorCount) {
                [$kw, $url] = $pairs[$pairIndex++];

                $anchor = '<a href="'.e($url).'" target="_blank"'.$relPart.'>'.e($kw).'</a>';

                $inner .= ' '.$anchor;
            }

            $paragraphs[0] = $openTag.$inner.'</p>';
        }

        // --------------------------------------------------
        // 4) Rebuild HTML
        // --------------------------------------------------
        $html = implode("\n", $paragraphs);

        return [$title, $html];
    }

    /**
     * Finds a safe insertion index inside an HTML string:
     * - NOT inside a tag: <...>
     * - NOT in the middle of a word (must land on boundary: space/punct)
     */
    // private function findSafeHtmlInsertPos(string $html, int $start): int
    // {
    //     $len = strlen($html);
    //     if ($len === 0) return 0;

    //     $start = max(0, min($start, $len));

    //     // boundary chars where insertion is "safe"
    //     $isBoundary = function ($ch) {
    //         return $ch === ' ' || $ch === "\n" || $ch === "\t"
    //             || $ch === '.' || $ch === ',' || $ch === ';' || $ch === ':'
    //             || $ch === '!' || $ch === '?' || $ch === ')' || $ch === '(';
    //     };

    //     // helper: check if index is inside an HTML tag
    //     $insideTagAt = function (int $pos) use ($html) {
    //         $before = substr($html, 0, $pos);
    //         $lastLt = strrpos($before, '<');
    //         if ($lastLt === false) return false;

    //         $lastGt = strrpos($before, '>');
    //         // if last '<' comes after last '>', we're inside a tag
    //         return $lastGt === false || $lastLt > $lastGt;
    //     };

    //     // scan outward from start to find nearest safe boundary
    //     for ($d = 0; $d < 200; $d++) {
    //         $right = $start + $d;
    //         if ($right < $len && !$insideTagAt($right)) {
    //             // $ch = $html[$right];
    //             $ch = substr($html, $right, 1);
    //             if ($isBoundary($ch)) {
    //                 // insert AFTER boundary (so we don't split punctuation)
    //                 return min($right + 1, $len);
    //             }
    //         }

    //         $left = $start - $d;
    //         if ($left > 0 && !$insideTagAt($left)) {
    //             // $ch = $html[$left];
    //             $ch = substr($html, $left, 1);
    //             if ($isBoundary($ch)) {
    //                 return min($left + 1, $len);
    //             }
    //         }
    //     }

    //     // fallback: if nothing found, append near end (but not inside tag)
    //     $pos = min($start, $len);
    //     while ($pos < $len && $insideTagAt($pos)) $pos++;
    //     return min($pos, $len);
    // }

    private function findSafeHtmlInsertPos(string $html, int $start): int
    {
        // ✅ Use mb_strlen for character-based length (critical for Chinese/Thai/Arabic)
        $len = mb_strlen($html);
        if ($len === 0) {
            return 0;
        }

        $start = max(0, min($start, $len));

        // boundary chars where insertion is safe
        $isBoundary = function ($ch) {
            return $ch === ' ' || $ch === "\n" || $ch === "\t"
                || $ch === '.' || $ch === ',' || $ch === ';' || $ch === ':'
                || $ch === '!' || $ch === '?' || $ch === ')' || $ch === '(';
        };

        // check if position is inside an HTML tag
        $insideTagAt = function (int $pos) use ($html) {
            $before = mb_substr($html, 0, $pos);
            $lastLt = mb_strrpos($before, '<');
            if ($lastLt === false) {
                return false;
            }

            $lastGt = mb_strrpos($before, '>');

            return $lastGt === false || $lastLt > $lastGt;
        };

        // scan outward from start
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

        // fallback: append safely near end
        $pos = min($start, $len);
        while ($pos < $len && $insideTagAt($pos)) {
            $pos++;
        }

        return min($pos, $len);
    }

    private function markRemoteAttemptStarted(CampaignPost $post): bool
    {
        return DB::transaction(function () use ($post) {
            $fresh = CampaignPost::lockForUpdate()->find($post->id);

            if (! $this->stillOwns($fresh, $post)) {
                return false;
            }

            $fresh->update([
                'delivery_state' => CampaignPost::DELIVERY_REMOTE_UNKNOWN,
                'last_failure_code' => null,
            ]);

            return true;
        });
    }

    private function stillOwns(?CampaignPost $fresh, CampaignPost $claimed): bool
    {
        return $fresh !== null
            && $fresh->lock_token === $claimed->lock_token
            && $fresh->dispatch_generation === $this->dispatchGeneration;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function classifyDeliveryFailure(Throwable $exception): array
    {
        $message = mb_strtolower($exception->getMessage());

        if (preg_match('/curl error\s*6\b/', $message)
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'getaddrinfo failed')
            || str_contains($message, 'name or service not known')) {
            return ['dns_resolution_failed', CampaignPost::DELIVERY_REMOTE_ABSENT];
        }

        if (preg_match('/curl error\s*7\b/', $message)
            || ($exception instanceof ConnectionException && (
                str_contains($message, "couldn't connect")
                || str_contains($message, 'could not connect')
                || str_contains($message, 'failed to connect')
                || str_contains($message, 'connection refused')
            ))) {
            return ['connection_failed', CampaignPost::DELIVERY_REMOTE_ABSENT];
        }

        if (preg_match('/curl error\s*28\b/', $message)
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')) {
            return ['connection_timeout', CampaignPost::DELIVERY_REMOTE_UNKNOWN];
        }

        if (preg_match('/wp api failed \((\d{3})\)/', $message, $matches)) {
            return [(int) $matches[1] >= 500 ? 'http_server_error' : 'http_client_error', CampaignPost::DELIVERY_REMOTE_UNKNOWN];
        }

        if (str_contains($message, 'non-json response')
            || (str_contains($message, 'malformed') && str_contains($message, 'response'))) {
            return ['malformed_response', CampaignPost::DELIVERY_REMOTE_UNKNOWN];
        }

        return ['publish_failed', null];
    }

    private function classifyFailure(Throwable $exception): array
    {
        return self::classifyDeliveryFailure($exception);
    }

    private function postToWordPress(CampaignPost $post, string $title, string $content): array
    {
        $domain = trim((string) $post->campaignDomain->domain->name);

        // ✅ Ensure scheme
        if (! preg_match('~^https?://~i', $domain)) {
            $domain = 'https://'.$domain;
        }

        $endpoint = rtrim($domain, '/').'/wp-json/external/v1/posts/create';

        $payload = [
            'title' => $title,
            'content' => $content,
            'status' => 'publish',
            'post_type' => 'post',
            'is_sticky' => $post->is_sticky,
            'api_key' => (string) $post->campaignDomain->domain->api_key,
        ];

        // ✅ UTF-8 Safe: Use proper headers and ensure payload is clean
        $res = Http::withoutVerifying()
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

        if (! isset($json['post_id']) || (array_key_exists('success', $json) && $json['success'] !== true)) {
            throw new \Exception('WP API returned malformed response');
        }

        return $json;
    }

    /**
     * 🏁 Finalize campaign if all posts processed
     */
    private function finalizeCampaignIfDone(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
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
