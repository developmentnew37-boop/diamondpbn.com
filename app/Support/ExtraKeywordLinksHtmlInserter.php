<?php

namespace App\Support;

use App\Services\CampaignPostContentBuilder;

/**
 * Inserts extra keyword links inline inside existing block markup so anchors read as part of the prose,
 * not as separate lines between paragraphs.
 */
class ExtraKeywordLinksHtmlInserter
{
    /**
     * @param  list<array{0: string, 1?: string}>  $pairs  [keyword, url]
     */
    public static function insertBetweenParagraphs(string $html, array $pairs, string $relAttr): string
    {
        $anchors = [];
        foreach ($pairs as $pair) {
            if (count($pair) < 2) {
                continue;
            }
            $kw  = (string) ($pair[0] ?? '');
            $url = (string) ($pair[1] ?? '');
            if ($kw === '' && $url === '') {
                continue;
            }
            $anchors[] = '<a href="' . e($url) . '" target="_blank" rel="' . e($relAttr) . '">' . e($kw) . '</a>';
        }
        if (count($anchors) === 0) {
            return $html;
        }

        if (preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $html, $matches, PREG_SET_ORDER) && count($matches) > 0) {
            $paraCount = count($matches);
            $b         = count($anchors);

            $lengths = [];
            foreach ($matches as $m) {
                $full  = $m[0];
                $inner = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $full);
                $lengths[] = mb_strlen(strip_tags((string) $inner));
            }

            /** @var array<int, list<string>> $byPara */
            $byPara = [];
            for ($j = 0; $j < $b; $j++) {
                $p = self::pickParagraphIndexForAnchor($j, $b, $paraCount, $lengths);
                if (! isset($byPara[$p])) {
                    $byPara[$p] = [];
                }
                $byPara[$p][] = $anchors[$j];
            }

            $idx = 0;

            return (string) preg_replace_callback('/<p\b[^>]*>.*?<\/p>/is', static function (array $m) use (&$idx, $byPara): string {
                $p = $idx++;
                if (empty($byPara[$p])) {
                    return $m[0];
                }
                $full = $m[0];
                preg_match('/^<p\b[^>]*>/i', $full, $openTagMatch);
                $openTag = $openTagMatch[0] ?? '<p>';
                $inner   = preg_replace('/^<p\b[^>]*>|<\/p>$/i', '', $full);
                $target  = (int) max(20, floor(strlen($inner) * 0.22));

                foreach ($byPara[$p] as $anchorHtml) {
                    $safePos = CampaignPostContentBuilder::findSafeHtmlInsertPos($inner, $target);
                    $inner   = substr($inner, 0, $safePos) . ' ' . $anchorHtml . ' ' . substr($inner, $safePos);
                    $target  = $safePos + strlen($anchorHtml) + 40;
                }

                return $openTag . $inner . '</p>';
            }, $html);
        }

        // Block markup without <p>…</p>: try first substantial <div>…</div>
        if (preg_match_all('/<div\b[^>]*>.*?<\/div>/is', $html, $divMatches, PREG_SET_ORDER) && count($divMatches) >= 1) {
            $full = $divMatches[0][0];
            preg_match('/^<div\b[^>]*>/i', $full, $openTagMatch);
            $openTag = $openTagMatch[0] ?? '<div>';
            $inner   = preg_replace('/^<div\b[^>]*>|<\/div>$/i', '', $full);
            $target  = (int) max(20, floor(strlen($inner) * 0.22));
            foreach ($anchors as $anchorHtml) {
                $safePos = CampaignPostContentBuilder::findSafeHtmlInsertPos($inner, $target);
                $inner   = substr($inner, 0, $safePos) . ' ' . $anchorHtml . ' ' . substr($inner, $safePos);
                $target  = $safePos + strlen($anchorHtml) + 40;
            }
            $replacement = $openTag . $inner . '</div>';

            return (string) preg_replace('/<div\b[^>]*>.*?<\/div>/is', $replacement, $html, 1);
        }

        return $html . "\n" . '<p>' . implode(' ', $anchors) . '</p>';
    }

    /**
     * Spread anchors across paragraph indices; prefer paragraphs with enough visible text.
     *
     * @param  list<int>  $lengths  strip_tags length per paragraph
     */
    private static function pickParagraphIndexForAnchor(int $j, int $anchorCount, int $paraCount, array $lengths): int
    {
        if ($paraCount === 1) {
            return 0;
        }

        $base = (int) round(($j + 1) * ($paraCount - 1) / ($anchorCount + 1));
        $base = max(0, min($paraCount - 1, $base));

        if (($lengths[$base] ?? 0) >= 28) {
            return $base;
        }

        for ($d = 1; $d < $paraCount; $d++) {
            $right = $base + $d;
            if ($right < $paraCount && ($lengths[$right] ?? 0) >= 28) {
                return $right;
            }
            $left = $base - $d;
            if ($left >= 0 && ($lengths[$left] ?? 0) >= 28) {
                return $left;
            }
        }

        $best = 0;
        $bestLen = -1;
        for ($i = 0; $i < $paraCount; $i++) {
            if (($lengths[$i] ?? 0) > $bestLen) {
                $bestLen = $lengths[$i];
                $best    = $i;
            }
        }

        return $best;
    }

    /**
     * Remove anchors managed by this app (target="_blank").
     */
    public static function stripBlankTargetAnchors(string $html): string
    {
        $pattern = '/<a\s[^>]*target\s*=\s*["\']_blank["\'][^>]*>.*?<\/a>/is';

        return (string) preg_replace($pattern, '', $html);
    }

    /**
     * Replace target="_blank" anchors in document order with keyword/url pairs from the DB.
     * If the remote HTML has more such anchors than pairs (e.g. user removed a row in admin),
     * extras are removed — they must not be overwritten with the last pair (duplicate links).
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    public static function replaceBlankAnchorsWithPairs(string $html, array $pairs, string $relAttr): string
    {
        if (count($pairs) === 0) {
            return $html;
        }

        $pattern = '/<a\s[^>]*target\s*=\s*["\']_blank["\'][^>]*>.*?<\/a>/is';
        $index   = 0;
        $n       = count($pairs);

        return (string) preg_replace_callback($pattern, function () use ($pairs, &$index, $relAttr, $n) {
            if ($index < $n) {
                $pair = $pairs[$index];
                $index++;

                return '<a href="' . e($pair[1]) . '" target="_blank" rel="' . e($relAttr) . '">' . e($pair[0]) . '</a>';
            }
            $index++;

            return '';
        }, $html);
    }
}
