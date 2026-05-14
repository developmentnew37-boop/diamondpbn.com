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
            $html  = trim((string) $article->description);
        } else {
            $title = trim((string) ($ca->article_title_snapshot ?? ''));
            $html  = trim((string) ($ca->article_body_snapshot ?? ''));
        }

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

        if (!is_array($keywords) || !is_array($urls)) {
            throw new \Exception("Invalid keyword/url format");
        }

        $pairs = [];
        $max   = min(count($keywords), count($urls));
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

        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
        $paragraphs = $matches[0] ?? [];
        if (count($paragraphs) === 0) {
            $paragraphs = ['<p>' . $html . '</p>'];
        }

        $paraCount   = count($paragraphs);
        $anchorCount = count($pairs);
        $base        = intdiv($anchorCount, $paraCount);
        $remainder   = $anchorCount % $paraCount;

        $pairIndex = 0;
        $nofollow  = (bool) ($ca->nofollow ?? false);
        $sponsored = (bool) ($ca->sponsored ?? false);
        $relTokens = [];
        if ($nofollow) {
            $relTokens[] = 'nofollow';
        }
        if ($sponsored) {
            $relTokens[] = 'sponsored';
        }
        $relPart = count($relTokens) > 0 ? ' rel="' . implode(' ', $relTokens) . '"' : '';

        for ($p = 0; $p < $paraCount && $pairIndex < $anchorCount; $p++) {
            $insertCount = $base + ($p < $remainder ? 1 : 0);
            if ($insertCount <= 0) {
                continue;
            }

            $paraHtml = $paragraphs[$p];
            preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';
            $inner   = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);
            $target  = (int) max(20, floor(strlen($inner) * 0.20));

            for ($k = 0; $k < $insertCount && $pairIndex < $anchorCount; $k++) {
                [$kw, $url] = $pairs[$pairIndex++];
                $anchor   = '<a href="' . e($url) . '" target="_blank"' . $relPart . '>' . e($kw) . '</a>';
                $safePos  = self::findSafeHtmlInsertPos($inner, $target);
                $inner    = substr($inner, 0, $safePos) . ' ' . $anchor . ' ' . substr($inner, $safePos);
                $target   = $safePos + strlen($anchor) + 40;
            }

            $paragraphs[$p] = $openTag . $inner . '</p>';
        }

        if ($pairIndex < $anchorCount) {
            usort($paragraphs, fn ($a, $b) => mb_strlen(strip_tags($b)) <=> mb_strlen(strip_tags($a)));
            $fallback = $paragraphs[0];
            preg_match('/^<p\b[^>]*>/i', $fallback, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<p>';
            $inner   = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $fallback);
            while ($pairIndex < $anchorCount) {
                [$kw, $url] = $pairs[$pairIndex++];
                $anchor = '<a href="' . e($url) . '" target="_blank"' . $relPart . '>' . e($kw) . '</a>';
                $inner .= ' ' . $anchor;
            }
            $paragraphs[0] = $openTag . $inner . '</p>';
        }

        $html = implode("\n", $paragraphs);

        return [$title, $html];
    }

    public static function findSafeHtmlInsertPos(string $html, int $start): int
    {
        $len   = strlen($html);
        if ($len === 0) {
            return 0;
        }
        $start = max(0, min($start, $len));

        $isBoundary = fn ($ch) => in_array($ch, [' ', "\n", "\t", '.', ',', ';', ':', '!', '?', ')', '('], true);

        $insideTagAt = function (int $pos) use ($html): bool {
            $before = substr($html, 0, $pos);
            $lastLt = strrpos($before, '<');
            if ($lastLt === false) {
                return false;
            }
            $lastGt = strrpos($before, '>');
            return $lastGt === false || $lastLt > $lastGt;
        };

        for ($d = 0; $d < 200; $d++) {
            $right = $start + $d;
            if ($right < $len && !$insideTagAt($right)) {
                $ch = substr($html, $right, 1);
                if ($ch !== '' && $isBoundary($ch)) {
                    return min($right + 1, $len);
                }
            }
            $left = $start - $d;
            if ($left > 0 && !$insideTagAt($left)) {
                $ch = substr($html, $left, 1);
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
