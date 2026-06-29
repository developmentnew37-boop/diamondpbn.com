<?php

namespace App\Services;

use App\Models\Admin\CampaignPost;

/**
 * Builds post title and HTML content from a campaign post (article + keyword/url from campaign_article).
 * Used by PublishCampaignPostJob and by batch keyword/url update so remote posts can be refreshed.
 */
class CampaignPostContentBuilder
{
    /**
     * Returns [title, content] for the given campaign post.
     *
     * @return array{0: string, 1: string}
     */
    public static function build(CampaignPost $post): array
    {
        $post->loadMissing(['campaignArticle.article', 'campaignArticle']);

        $ca = $post->campaignArticle;
        if (! $ca) {
            throw new \Exception("Campaign article not found for campaign_post_id={$post->id}");
        }

        $article = $ca->article;
        if ($article) {
            $title = trim((string) $article->name);
            $html = trim((string) $article->description);
        } else {
            $title = trim((string) ($ca->article_title_snapshot ?? ''));
            $html = trim((string) ($ca->article_body_snapshot ?? ''));
        }

        // ✅ UTF-8 Sanitization - clean malformed bytes from article content
        $title = cleanUtf8($title, [
            'context' => 'content_builder',
            'article_id' => $article?->id,
            'field' => 'title',
            'log' => false, // Already logged at model level
        ]);

        $html = cleanUtf8($html, [
            'context' => 'content_builder',
            'article_id' => $article?->id,
            'field' => 'html',
            'log' => false, // Already logged at model level
        ]);

        if ($title === '' || $html === '') {
            throw new \Exception(
                "Article content missing for campaign_post_id={$post->id} (link to library article removed; fill snapshots or restore article)."
            );
        }

        $keywords = $ca->keyword_type === 'json'
            ? json_decode($ca->keyword, true)
            : [$ca->keyword];

        $urls = $ca->url_type === 'json'
            ? json_decode($ca->url, true)
            : [$ca->url];

        if (! is_array($keywords) || ! is_array($urls)) {
            throw new \Exception('Invalid keyword/url format');
        }

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

        // Extract paragraphs - handle both <p> tags and <br> separated content
        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
        $paragraphs = $matches[0] ?? [];

        // If no <p> tags found or only empty <p> tags, try splitting by <br> tags
        if (count($paragraphs) === 0 || (count($paragraphs) === 1 && mb_strlen(strip_tags($paragraphs[0])) < 10)) {
            // Split content by <br> or <br/> or <br /> tags
            $parts = preg_split('/<br\s*\/?>/i', $html);
            $paragraphs = [];

            foreach ($parts as $part) {
                $part = trim($part);
                // Skip empty parts and very short parts (less than 20 chars)
                if ($part !== '' && mb_strlen(strip_tags($part)) > 20) {
                    $paragraphs[] = '<p>'.$part.'</p>';
                }
            }

            // If still no valid paragraphs, wrap entire content
            if (count($paragraphs) === 0) {
                $paragraphs = ['<p>'.$html.'</p>'];
            }
        }

        $paraCount = count($paragraphs);
        $anchorCount = count($pairs);
        $base = intdiv($anchorCount, $paraCount);
        $remainder = $anchorCount % $paraCount;

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

        for ($p = 0; $p < $paraCount && $pairIndex < $anchorCount; $p++) {
            $insertCount = $base + ($p < $remainder ? 1 : 0);
            if ($insertCount <= 0) {
                continue;
            }

            $paraHtml = $paragraphs[$p];
            preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';
            $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);
            // ✅ Use mb_strlen for character count, not byte count (critical for Chinese/Thai/Arabic)
            $target = (int) max(20, floor(mb_strlen($inner) * 0.20));

            for ($k = 0; $k < $insertCount && $pairIndex < $anchorCount; $k++) {
                [$kw, $url] = $pairs[$pairIndex++];
                $anchor = '<a href="'.e($url).'" target="_blank"'.$relPart.'>'.e($kw).'</a>';
                $safePos = self::findSafeHtmlInsertPos($inner, $target);
                // ✅ Use mb_substr to avoid splitting multi-byte UTF-8 characters
                $inner = mb_substr($inner, 0, $safePos).' '.$anchor.' '.mb_substr($inner, $safePos);
                $target = $safePos + mb_strlen($anchor) + 40;
            }

            $paragraphs[$p] = $openTag.$inner.'</p>';
        }

        if ($pairIndex < $anchorCount) {
            usort($paragraphs, fn ($a, $b) => mb_strlen(strip_tags($b)) <=> mb_strlen(strip_tags($a)));
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

        $html = implode("\n", $paragraphs);

        // ✅ Auto-wrap RTL content with direction attribute for proper WordPress display
        $html = wrapRtlContent($html, $title);

        return [$title, $html];
    }

    public static function findSafeHtmlInsertPos(string $html, int $start): int
    {
        // Note: This function works with byte positions for HTML parsing
        // The $start parameter should be a character position, so we convert it
        $len = mb_strlen($html);
        if ($len === 0) {
            return 0;
        }
        $start = max(0, min($start, $len));

        $isBoundary = fn ($ch) => in_array($ch, [' ', "\n", "\t", '.', ',', ';', ':', '!', '?', ')', '('], true);

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
                $ch = mb_substr($html, $right, 1);
                if ($ch !== '' && $isBoundary($ch)) {
                    return min($right + 1, $len);
                }
            }
            $left = $start - $d;
            if ($left > 0 && ! $insideTagAt($left)) {
                $ch = mb_substr($html, $left, 1);
                if ($ch !== '' && $isBoundary($ch)) {
                    return min($left + 1, $len);
                }
            }
        }

        $pos = min($start, $len);
        while ($pos < $len && $insideTagAt($pos)) {
            $pos++;
        }

        return min($pos, $len);
    }
}
