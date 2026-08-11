<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BlogrollStatusApiService
{
    public function normalizeBaseUrl(string $domainName): string
    {
        $baseUrl = trim($domainName);
        if (! preg_match('~^https?://~i', $baseUrl)) {
            $baseUrl = 'https://'.$baseUrl;
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    public function draftEntry(string $domainName, string $apiKey, string $remoteId): array
    {
        return $this->postState($domainName, $apiKey, $remoteId, 'draft');
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    public function publishEntry(string $domainName, string $apiKey, string $remoteId): array
    {
        return $this->postState($domainName, $apiKey, $remoteId, 'publish');
    }

    /**
     * @return array{ok: bool, status: string|null, remote_url: string|null, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    public function fetchEntry(string $domainName, string $apiKey, string $remoteId): array
    {
        $baseUrl = $this->normalizeBaseUrl($domainName);
        $url = $baseUrl.'/wp-json/external/v1/blogroll/'.urlencode($remoteId).'?api_key='.urlencode($apiKey);

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->acceptJson()
            ->get($url);

        if ($response->successful()) {
            $json = $response->json();
            $body = is_array($json) ? $json : null;

            if (is_array($body)) {
                $nested = is_array($body['data'] ?? null) ? $body['data'] : $body;

                return [
                    'ok' => true,
                    'status' => $this->extractEntryStatus($nested),
                    'remote_url' => $this->extractRemoteUrl($nested),
                    'message' => 'OK',
                    'http_status' => $response->status(),
                    'body' => $body,
                ];
            }
        }

        $listResult = $this->fetchEntryFromList($domainName, $apiKey, $remoteId);
        if ($listResult['ok']) {
            return $listResult;
        }

        $httpStatus = $response->status();
        $json = $response->json();
        $body = is_array($json) ? $json : null;
        $message = is_array($body)
            ? (string) ($body['message'] ?? 'Could not fetch remote blogroll entry')
            : ($response->body() ?: 'Could not fetch remote blogroll entry');

        return [
            'ok' => false,
            'status' => null,
            'remote_url' => null,
            'message' => $message,
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function extractEntryStatus(array $body): ?string
    {
        foreach (['status', 'entry_status', 'visibility'] as $key) {
            $status = $body[$key] ?? null;
            if (is_string($status) && $status !== '') {
                return strtolower(trim($status));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function extractRemoteUrl(array $body): ?string
    {
        foreach (['remote_url', 'link', 'url'] as $key) {
            if (! empty($body[$key]) && is_string($body[$key])) {
                return $body[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array{status: string|null, remote_url: string|null}
     */
    public function extractFromMutationResponse(?array $body): array
    {
        if (! is_array($body)) {
            return ['status' => null, 'remote_url' => null];
        }

        $nested = is_array($body['data'] ?? null) ? $body['data'] : $body;

        return [
            'status' => $this->extractEntryStatus($nested),
            'remote_url' => $this->extractRemoteUrl($nested),
        ];
    }

    /**
     * @return array{ok: bool, status: string|null, remote_url: string|null, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    private function fetchEntryFromList(string $domainName, string $apiKey, string $remoteId): array
    {
        $baseUrl = $this->normalizeBaseUrl($domainName);
        $url = $baseUrl.'/wp-json/external/v1/blogroll?api_key='.urlencode($apiKey);

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->acceptJson()
            ->get($url);

        $httpStatus = $response->status();
        $json = $response->json();
        $body = is_array($json) ? $json : null;

        if (! $response->successful() || ! is_array($body)) {
            return [
                'ok' => false,
                'status' => null,
                'remote_url' => null,
                'message' => 'Could not fetch blogroll list',
                'http_status' => $httpStatus,
                'body' => $body,
            ];
        }

        $items = $body['data'] ?? $body;
        if (! is_array($items)) {
            return [
                'ok' => false,
                'status' => null,
                'remote_url' => null,
                'message' => 'Unexpected blogroll list format',
                'http_status' => $httpStatus,
                'body' => $body,
            ];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if ((string) ($item['id'] ?? '') === (string) $remoteId) {
                return [
                    'ok' => true,
                    'status' => $this->extractEntryStatus($item),
                    'remote_url' => $this->extractRemoteUrl($item),
                    'message' => 'OK',
                    'http_status' => $httpStatus,
                    'body' => $item,
                ];
            }
        }

        return [
            'ok' => false,
            'status' => null,
            'remote_url' => null,
            'message' => 'Blogroll entry not found in remote list',
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    private function postState(string $domainName, string $apiKey, string $remoteId, string $action): array
    {
        $baseUrl = $this->normalizeBaseUrl($domainName);
        $url = $baseUrl.'/wp-json/external/v1/blogroll/'.$action.'/'.urlencode($remoteId);

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->asJson()
            ->post($url, [
                'api_key' => $apiKey,
            ]);

        return $this->interpret($response);
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    private function interpret(Response $response): array
    {
        $httpStatus = $response->status();
        $json = $response->json();
        $body = is_array($json) ? $json : null;

        if ($response->successful()) {
            $action = is_array($body) ? (string) ($body['action'] ?? '') : '';
            $skipped = $action === 'skipped' || (is_array($body) && ! empty($body['skipped']));

            return [
                'ok' => true,
                'skipped' => $skipped,
                'message' => is_array($body) ? (string) ($body['message'] ?? 'OK') : 'OK',
                'http_status' => $httpStatus,
                'body' => $body,
            ];
        }

        $message = is_array($body)
            ? (string) ($body['message'] ?? $response->body())
            : $response->body();

        return [
            'ok' => false,
            'skipped' => false,
            'message' => $message !== '' ? $message : 'Remote request failed',
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }
}
