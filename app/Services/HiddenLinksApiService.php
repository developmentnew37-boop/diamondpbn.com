<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Hidden Links remote API. Endpoints: GET /hidden-links, POST /hidden-links/add,
 * POST /hidden-links/update/{index}, DELETE /hidden-links/delete/{index}.
 * Auth: api_key in URL or X-External-API-Key header.
 */
class HiddenLinksApiService
{
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
     * GET hidden links list. api_key in URL.
     * Returns ['status' => bool, 'message' => string, 'data' => array].
     */
    public static function fetchHiddenLinks(string $domain, string $apiKey): array
    {
        $base = self::baseUrl($domain);
        $url  = $base . '/wp-json/external/v1/hidden-links?api_key=' . urlencode($apiKey);

        $res = Http::withoutVerifying()
            ->timeout(120)
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
            'message' => (string) ($body['message'] ?? ''),
            'data'    => $data,
        ];
    }

    /**
     * Find 0-based index in data where entry 'id' matches remote_id.
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
     * POST .../hidden-links/update/{remote_id}. Body: keyword, link. Auth: X-External-API-Key header.
     * Uses remote_id in path per API (not array index). Timeout 120s for single update.
     */
    public static function updateEntry(string $domain, string $apiKey, string $remoteId, string $keyword, string $link): \Illuminate\Http\Client\Response
    {
        $base    = self::baseUrl($domain);
        $remoteId = trim((string) $remoteId);
        $endpoint = $base . '/wp-json/external/v1/hidden-links/update/' . $remoteId;

        return Http::withoutVerifying()
            ->timeout(120)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-External-API-Key' => $apiKey,
            ])
            ->post($endpoint, [
                'keyword' => $keyword,
                'link'    => $link,
            ]);
    }

    /**
     * DELETE .../hidden-links/delete/{remote_id}. Auth: X-External-API-Key header.
     * Uses remote_id in path per API (not array index). Timeout 120s for single delete.
     */
    public static function deleteEntry(string $domain, string $apiKey, string $remoteId): \Illuminate\Http\Client\Response
    {
        $base    = self::baseUrl($domain);
        $remoteId = trim((string) $remoteId);
        $url     = $base . '/wp-json/external/v1/hidden-links/delete/' . $remoteId;

        return Http::withoutVerifying()
            ->timeout(120)
            ->acceptJson()
            ->withHeaders([
                'X-External-API-Key' => $apiKey,
            ])
            ->delete($url);
    }
}
