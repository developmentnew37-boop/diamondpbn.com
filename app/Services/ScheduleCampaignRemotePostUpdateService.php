<?php

namespace App\Services;

use App\Models\Admin\ScheduleCampaignPost;
use Illuminate\Support\Facades\Http;

/**
 * Fetches post content from the remote WordPress site and applies new keyword/URL
 * from the database (e.g. after bulk edit). Used by BulkUpdateScheduleCampaignPostsJob.
 * Same API as WP Scheduled: GET/POST /wp-json/external/v1/posts/{id} and update.
 */
class ScheduleCampaignRemotePostUpdateService
{
    /**
     * Fetch current title and content from remote, replace link anchors with new keyword/URL from DB,
     * and return [title, content] ready to send to the update endpoint.
     *
     * @return array{0: string, 1: string}
     */
    public static function fetchAndApplyKeywordUrl(ScheduleCampaignPost $post): array
    {
        $domain = $post->campaignDomain?->domain;
        if (!$domain || !$domain->api_key || empty($post->remote_id)) {
            throw new \InvalidArgumentException('Missing domain, api_key or remote_id');
        }

        $baseUrl = self::normalizeBaseUrl((string) $domain->name);
        $getUrl = $baseUrl . '/wp-json/external/v1/posts/' . $post->remote_id
            . '?api_key=' . urlencode((string) $domain->api_key);

        $response = Http::withoutVerifying()->timeout(60)->acceptJson()->get($getUrl);
        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch post from remote: ' . $response->status());
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new \RuntimeException('Remote did not return JSON');
        }

        $title = (string) ($data['post_title'] ?? $data['title'] ?? $data['title']['rendered'] ?? '');
        $content = (string) ($data['post_content'] ?? $data['content'] ?? $data['content']['rendered'] ?? '');
        if ($content === '' && isset($data['content']['raw'])) {
            $content = (string) $data['content']['raw'];
        }

        $post->load('campaignArticle');
        $ca = $post->campaignArticle;
        if (!$ca) {
            throw new \RuntimeException('Campaign article not found');
        }

        $pairs = self::getKeywordUrlPairs($ca);
        if (count($pairs) === 0) {
            return [$title, $content];
        }

        $nofollow = (bool) ($ca->nofollow ?? false);
        $relAttr = $nofollow ? 'nofollow noopener' : 'noopener';
        $content = self::replaceAnchorsWithNewPairs($content, $pairs, $relAttr);

        return [$title, $content];
    }

    private static function normalizeBaseUrl(string $domain): string
    {
        $domain = trim($domain);
        if (!preg_match('~^https?://~i', $domain)) {
            $domain = 'https://' . $domain;
        }
        return rtrim($domain, '/');
    }

    /** @return list<array{0: string, 1: string}> */
    private static function getKeywordUrlPairs($campaignArticle): array
    {
        $keywords = ($campaignArticle->keyword_type ?? 'single') === 'json'
            ? json_decode($campaignArticle->keyword, true)
            : [$campaignArticle->keyword];
        $urls = ($campaignArticle->url_type ?? 'single') === 'json'
            ? json_decode($campaignArticle->url, true)
            : [$campaignArticle->url];
        if (!is_array($keywords) || !is_array($urls)) {
            return [];
        }
        $pairs = [];
        $max = min(count($keywords), count($urls));
        for ($i = 0; $i < $max; $i++) {
            $kw = trim((string) ($keywords[$i] ?? ''));
            $url = trim((string) ($urls[$i] ?? ''));
            if ($kw !== '' || $url !== '') {
                $pairs[] = [$kw, $url];
            }
        }
        return $pairs;
    }

    private static function replaceAnchorsWithNewPairs(string $html, array $pairs, string $relAttr): string
    {
        if (count($pairs) === 0) {
            return $html;
        }
        $pattern = '/<a\s[^>]*target\s*=\s*["\']_blank["\'][^>]*>.*?<\/a>/is';
        $index = 0;
        return preg_replace_callback($pattern, function () use ($pairs, &$index, $relAttr) {
            $pair = $pairs[min($index, count($pairs) - 1)];
            $index++;
            return '<a href="' . e($pair[1]) . '" target="_blank" rel="' . $relAttr . '">' . e($pair[0]) . '</a>';
        }, $html);
    }
}
