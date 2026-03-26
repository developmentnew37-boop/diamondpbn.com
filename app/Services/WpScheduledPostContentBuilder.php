<?php

namespace App\Services;

use App\Models\Admin\WpScheduledCampaignPost;

/**
 * Builds post title and HTML content from a WP Scheduled campaign post (article + keyword/url).
 * Used by PublishWpScheduledPostJob only. Bulk updates use WpScheduledRemotePostUpdateService
 * (fetch from remote, apply new keyword/URL, then update).
 */
class WpScheduledPostContentBuilder
{
    /**
     * Returns [title, content] for the given WP scheduled post.
     *
     * @return array{0: string, 1: string}
     */
    public static function build(WpScheduledCampaignPost $post): array
    {
        $article = $post->campaignArticle->article ?? null;
        if (!$article) {
            throw new \Exception("Article not found. post_id={$post->id}");
        }

        $title = trim((string) $article->name);
        $html = trim((string) $article->description);
        if ($title === '' || $html === '') {
            throw new \Exception("Article missing content");
        }

        $ca = $post->campaignArticle;
        $keywords = $ca->keyword_type === 'json' ? json_decode($ca->keyword, true) : [$ca->keyword];
        $urls = $ca->url_type === 'json' ? json_decode($ca->url, true) : [$ca->url];
        if (!is_array($keywords) || !is_array($urls)) {
            throw new \Exception("Invalid keyword/url format");
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
            throw new \Exception("No valid keyword/url pairs");
        }

        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
        $paragraphs = $matches[0] ?? [];
        if (count($paragraphs) === 0) {
            $paragraphs = ['<p>' . $html . '</p>'];
        }

        $anchorCount = count($pairs);
        shuffle($pairs);
        $paraIndexes = array_keys($paragraphs);
        shuffle($paraIndexes);
        $pairIndex = 0;
        $nofollow = (bool) ($ca->nofollow ?? false);
        $relAttr = $nofollow ? 'nofollow noopener' : 'noopener';

        while ($pairIndex < $anchorCount) {
            foreach ($paraIndexes as $p) {
                if ($pairIndex >= $anchorCount) {
                    break;
                }
                $paraHtml = $paragraphs[$p];
                preg_match('/^<p\b[^>]*>/i', $paraHtml, $openTagMatch);
                $openTag = $openTagMatch[0] ?? '<p>';
                $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $paraHtml);
                if (mb_strlen(trim(strip_tags($inner))) < 30) {
                    continue;
                }
                [$kw, $url] = $pairs[$pairIndex++];
                $anchor = '<a href="' . e($url) . '" target="_blank" rel="' . $relAttr . '">' . e($kw) . '</a>';
                $target = random_int((int) (strlen($inner) * 0.1), (int) (strlen($inner) * 0.3));
                $safePos = self::findSafeHtmlInsertPos($inner, $target);
                $inner = substr($inner, 0, $safePos) . ' ' . $anchor . ' ' . substr($inner, $safePos);
                $paragraphs[$p] = $openTag . $inner . '</p>';
            }
        }

        $html = implode("\n", $paragraphs);
        return [$title, $html];
    }

    private static function findSafeHtmlInsertPos(string $html, int $start): int
    {
        $len = strlen($html);
        if ($len === 0) {
            return 0;
        }
        $start = max(0, min($start, $len));
        $isBoundary = fn (string $ch): bool => in_array($ch, [' ', "\n", "\t", '.', ',', ';', ':', '!', '?', ')', '('], true);
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
