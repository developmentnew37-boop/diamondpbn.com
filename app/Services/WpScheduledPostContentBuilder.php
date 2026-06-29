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
        $ca = $post->campaignArticle;
        if (! $ca) {
            throw new \Exception("Campaign article not found. post_id={$post->id}");
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
            'context' => 'wp_scheduled_content_builder',
            'article_id' => $article?->id,
            'field' => 'title',
            'log' => false, // Already logged at model level
        ]);

        $html = cleanUtf8($html, [
            'context' => 'wp_scheduled_content_builder',
            'article_id' => $article?->id,
            'field' => 'html',
            'log' => false, // Already logged at model level
        ]);

        if ($title === '' || $html === '') {
            throw new \Exception(
                "Article content missing for post_id={$post->id} (library article removed; snapshots required)."
            );
        }
        $keywords = $ca->keyword_type === 'json' ? json_decode($ca->keyword, true) : [$ca->keyword];
        $urls = $ca->url_type === 'json' ? json_decode($ca->url, true) : [$ca->url];
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

        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches);
        $paragraphs = $matches[0] ?? [];
        if (count($paragraphs) === 0) {
            $paragraphs = ['<p>'.$html.'</p>'];
        }

        $anchorCount = count($pairs);
        shuffle($pairs);
        $paraIndexes = array_keys($paragraphs);
        shuffle($paraIndexes);
        $pairIndex = 0;

        // ✅ PRIORITY 1: Use raw_rel_attr if present (from Raw HTML Anchors mode - supports ANY rel values)
        // ✅ PRIORITY 2: Build from individual boolean fields (from checkbox mode - backward compatibility)
        $relAttr = '';
        if (! empty($ca->raw_rel_attr)) {
            // Raw HTML mode: Use full rel string directly (supports custom values like "external", "bookmark")
            $relAttr = trim($ca->raw_rel_attr);
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

            $relAttr = implode(' ', $relTokens);
        }

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
                $anchor = '<a href="'.e($url).'" target="_blank" rel="'.$relAttr.'">'.e($kw).'</a>';
                // ✅ Use mb_strlen for character count, not byte count (critical for Chinese/Thai/Arabic)
                $target = random_int((int) (mb_strlen($inner) * 0.1), (int) (mb_strlen($inner) * 0.3));
                $safePos = self::findSafeHtmlInsertPos($inner, $target);
                // ✅ Use mb_substr to avoid splitting multi-byte UTF-8 characters
                $inner = mb_substr($inner, 0, $safePos).' '.$anchor.' '.mb_substr($inner, $safePos);
                $paragraphs[$p] = $openTag.$inner.'</p>';
            }
        }

        $html = implode("\n", $paragraphs);

        // ✅ Auto-wrap RTL content with direction attribute for proper WordPress display
        $html = wrapRtlContent($html, $title);

        return [$title, $html];
    }

    private static function findSafeHtmlInsertPos(string $html, int $start): int
    {
        // ✅ Use mb_strlen for character-based length (critical for Chinese/Thai/Arabic)
        $len = mb_strlen($html);
        if ($len === 0) {
            return 0;
        }
        $start = max(0, min($start, $len));
        $isBoundary = fn (string $ch): bool => in_array($ch, [' ', "\n", "\t", '.', ',', ';', ':', '!', '?', ')', '('], true);
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
