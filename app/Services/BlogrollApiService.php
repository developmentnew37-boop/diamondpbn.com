<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Blogroll remote API: GET (api_key in URL), POST/PATCH/DELETE (api_key in body).
 * Base URL: use domain from our DB (e.g. fcb8casino.com or https://fcb8casino.com).
 */
class BlogrollApiService
{
    /**
     * Normalize domain to full base URL (with scheme).
     */
    public static function baseUrl(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            throw new \InvalidArgumentException('Domain cannot be empty');
        }
        if (!preg_match('~^https?://~i', $domain)) {
            $domain = 'https://' . $domain;
        }
        return rtrim($domain, '/');
    }

    /**
     * GET blogroll list. api_key in URL.
     * Returns ['status' => bool, 'message' => string, 'data' => array].
     */
    public static function fetchBlogroll(string $domain, string $apiKey): array
    {
        $base = self::baseUrl($domain);
        $url  = $base . '/wp-json/external/v1/blogroll?api_key=' . urlencode($apiKey);

        $res = Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->get($url);

        $body = $res->json();
        if (!is_array($body)) {
            return ['status' => false, 'message' => $res->body(), 'data' => []];
        }

        $data = $body['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        return [
            'status'  => (bool) ($body['status'] ?? false),
            'message'  => (string) ($body['message'] ?? ''),
            'data'     => $data,
        ];
    }

    /**
     * Find array index in blogroll data where entry 'id' matches remote_id.
     * Returns 0-based index or null if not found.
     */
    public static function findIndexByRemoteId(array $data, string $remoteId): ?int
    {
        $remoteId = trim($remoteId);
        foreach ($data as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = $entry['id'] ?? null;
            if ($id !== null && (string) $id === $remoteId) {
                return (int) $index;
            }
        }
        return null;
    }

    /**
     * Update blogroll entry at given index. api_key in body.
     * URL: .../blogroll/update/{index}
     */
    public static function updateEntry(string $domain, string $apiKey, int $index, string $keyword, string $link): \Illuminate\Http\Client\Response
    {
        $base     = self::baseUrl($domain);
        $endpoint = $base . '/wp-json/external/v1/blogroll/update/' . $index;

        return Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->patch($endpoint, [
                'api_key' => $apiKey,
                'keyword' => $keyword,
                'link'    => $link,
            ]);
    }

    /**
     * Update blogroll entry by remote id (from GET /blogroll). POST .../blogroll/update/{id}.
     * Use this for bulk update and single update when task.remote_id is available.
     */
    public static function updateEntryByRemoteId(string $domain, string $apiKey, string $remoteId, string $keyword, string $link): \Illuminate\Http\Client\Response
    {
        $base    = self::baseUrl($domain);
        $endpoint = $base . '/wp-json/external/v1/blogroll/update/' . urlencode($remoteId);

        return Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'api_key' => $apiKey,
                'keyword' => $keyword,
                'link'    => $link,
            ]);
    }

    /**
     * Delete blogroll entry at given index.
     * Tries GET with api_key in URL first (same convention as fetch); falls back to POST with api_key in body.
     */
    public static function deleteEntry(string $domain, string $apiKey, int $index): \Illuminate\Http\Client\Response
    {
        $base = self::baseUrl($domain);
        $url  = $base . '/wp-json/external/v1/blogroll/delete/' . $index . '?api_key=' . urlencode($apiKey);

        $res = Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->get($url);

        if ($res->successful()) {
            return $res;
        }

        // Fallback: POST with api_key in body (in case remote expects POST)
        $endpoint = $base . '/wp-json/external/v1/blogroll/delete/' . $index;
        return Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, ['api_key' => $apiKey]);
    }

    /**
     * Delete blogroll entry by remote id. DELETE .../blogroll/delete/{id}?api_key=...
     * Use for bulk delete and campaign delete when task.remote_id is available.
     */
    public static function deleteEntryByRemoteId(string $domain, string $apiKey, string $remoteId): \Illuminate\Http\Client\Response
    {
        $base = self::baseUrl($domain);
        $url  = $base . '/wp-json/external/v1/blogroll/delete/' . urlencode($remoteId) . '?api_key=' . urlencode($apiKey);

        return Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->delete($url);
    }
}
