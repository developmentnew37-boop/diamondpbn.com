<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Fetch post from remote and replace keyword/url in HTML.
 * Used when the local article may have been deleted after publish.
 */
class RemotePostUpdateService
{
    /**
     * Fetch post title and content from remote.
     * GET {baseUrl}/wp-json/external/v1/posts/{remoteId}?api_key=...
     *
     * @return array{post_title: string, post_content: string}|null
     */
    public static function fetchPost(string $baseUrl, string $apiKey, string $remoteId): ?array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (!preg_match('~^https?://~i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        $url = $baseUrl . '/wp-json/external/v1/posts/' . $remoteId . '?api_key=' . urlencode($apiKey);

        $response = Http::withoutVerifying()->timeout(120)->get($url);
        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data)) {
            return null;
        }

        return [
            'post_title'   => (string) ($data['post_title'] ?? ''),
            'post_content' => (string) ($data['post_content'] ?? ''),
        ];
    }

    /**
     * Replace in HTML the anchor that has old keyword (text) and old url (href) with new keyword and new url.
     */
    public static function replaceKeywordUrlInHtml(string $html, string $oldKeyword, string $oldUrl, string $newKeyword, string $newUrl): string
    {
        if ($oldKeyword === '' && $oldUrl === '') {
            return $html;
        }
        $oldUrlQuoted = preg_quote(rtrim($oldUrl, '/'), '/');
        $oldKwQuoted  = preg_quote($oldKeyword, '/');
        $pattern = '/<a\s+([^>]*?)href\s*=\s*(["\'])'
            . $oldUrlQuoted
            . '(?:\/)?\\2([^>]*)>\s*'
            . $oldKwQuoted
            . '\s*<\/a>/iu';
        $replacement = '<a $1href=$2' . $newUrl . '$2$3>' . $newKeyword . '</a>';
        return (string) preg_replace($pattern, $replacement, $html);
    }

    /**
     * Remove from HTML the anchor that has the given keyword (text) and url (href).
     */
    public static function removeKeywordUrlFromHtml(string $html, string $keyword, string $url): string
    {
        if ($keyword === '' && $url === '') {
            return $html;
        }
        $urlQuoted = preg_quote(rtrim($url, '/'), '/');
        $kwQuoted  = preg_quote($keyword, '/');
        $pattern   = '/<a\s+[^>]*?href\s*=\s*(["\'])'
            . $urlQuoted
            . '(?:\/)?\\1[^>]*>\s*'
            . $kwQuoted
            . '\s*<\/a>/iu';
        return (string) preg_replace($pattern, '', $html);
    }

    /**
     * Append new keyword/url links to post content.
     * add_pairs: array of [keyword, url]
     */
    public static function appendLinksToHtml(string $html, array $addPairs): string
    {
        if (count($addPairs) === 0) {
            return $html;
        }
        $links = [];
        foreach ($addPairs as $pair) {
            if (count($pair) >= 2 && ($pair[0] !== '' || $pair[1] !== '')) {
                $url  = htmlspecialchars($pair[1], ENT_QUOTES, 'UTF-8');
                $text = htmlspecialchars($pair[0], ENT_QUOTES, 'UTF-8');
                $links[] = '<a href="' . $url . '" target="_blank" rel="noopener">' . $text . '</a>';
            }
        }
        if (count($links) === 0) {
            return $html;
        }
        return $html . "\n" . '<p>' . implode(' ', $links) . '</p>';
    }

    /**
     * Apply multiple replacements, removals, and additions to post content.
     * replace_pairs: array of [old_keyword, old_url, new_keyword, new_url]
     * remove_pairs: array of [keyword, url]
     * add_pairs: array of [keyword, url]
     */
    public static function applyChangesToHtml(string $html, array $replacePairs, array $removePairs = [], array $addPairs = []): string
    {
        foreach ($replacePairs as $pair) {
            if (count($pair) >= 4) {
                $html = self::replaceKeywordUrlInHtml($html, $pair[0], $pair[1], $pair[2], $pair[3]);
            }
        }
        foreach ($removePairs as $pair) {
            if (count($pair) >= 2) {
                $html = self::removeKeywordUrlFromHtml($html, $pair[0], $pair[1]);
            }
        }
        $html = self::appendLinksToHtml($html, $addPairs);
        return $html;
    }
}
