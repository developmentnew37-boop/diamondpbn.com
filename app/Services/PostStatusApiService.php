<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PostStatusApiService
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
    public function draftPost(string $domainName, string $apiKey, string $remoteId): array
    {
        return $this->postState($domainName, $apiKey, $remoteId, 'draft');
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    public function publishPost(string $domainName, string $apiKey, string $remoteId): array
    {
        return $this->postState($domainName, $apiKey, $remoteId, 'publish');
    }

    /**
     * @param  bool  $useScheduleTime  If true, sends schedule_time (WordPress often sets status "future"). If false, only post_date — use when the slot is due and the post should go live now.
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    public function updatePostDate(
        string $domainName,
        string $apiKey,
        string $remoteId,
        string $postDateYmdHis,
        bool $useScheduleTime = true,
    ): array {
        $baseUrl = $this->normalizeBaseUrl($domainName);
        $url = $baseUrl.'/wp-json/external/v1/posts/update/'.$remoteId;

        $payload = [
            'api_key' => $apiKey,
            'post_date' => $postDateYmdHis,
        ];

        if ($useScheduleTime) {
            $payload['schedule_time'] = $postDateYmdHis;
        }

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->asJson()
            ->post($url, $payload);

        return $this->interpret($response);
    }

    /**
     * @return array{ok: bool, status: string|null, remote_url: string|null, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    public function fetchPost(string $domainName, string $apiKey, string $remoteId): array
    {
        $baseUrl = $this->normalizeBaseUrl($domainName);
        $url = $baseUrl.'/wp-json/external/v1/posts/'.$remoteId.'?api_key='.urlencode($apiKey);

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->acceptJson()
            ->get($url);

        $httpStatus = $response->status();
        $json = $response->json();
        $body = is_array($json) ? $json : null;

        if (! $response->successful() || ! is_array($body)) {
            $message = is_array($body)
                ? (string) ($body['message'] ?? 'Could not fetch remote post')
                : ($response->body() ?: 'Could not fetch remote post');

            return [
                'ok' => false,
                'status' => null,
                'remote_url' => null,
                'message' => $message,
                'http_status' => $httpStatus,
                'body' => $body,
            ];
        }

        return [
            'ok' => true,
            'status' => $this->extractPostStatus($body),
            'remote_url' => $this->extractRemoteUrl($body),
            'message' => 'OK',
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function extractPostStatus(array $body): ?string
    {
        $status = $body['post_status'] ?? $body['status'] ?? null;
        if (is_string($status) && $status !== '') {
            return strtolower(trim($status));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function extractRemoteUrl(array $body): ?string
    {
        foreach (['remote_url', 'link', 'permalink', 'url'] as $key) {
            if (! empty($body[$key]) && is_string($body[$key])) {
                return $body[$key];
            }
        }

        if (! empty($body['guid']) && is_string($body['guid'])) {
            return $body['guid'];
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

        $status = $this->extractPostStatus($nested);
        if ($status === null && isset($nested['status']) && is_string($nested['status'])) {
            $status = strtolower(trim($nested['status']));
        }

        return [
            'status' => $status,
            'remote_url' => $this->extractRemoteUrl($nested),
        ];
    }

    /**
     * @return array{ok: bool, skipped: bool, message: string, http_status: int|null, body: array<string, mixed>|null}
     */
    private function postState(string $domainName, string $apiKey, string $remoteId, string $action): array
    {
        $baseUrl = $this->normalizeBaseUrl($domainName);
        $url = $baseUrl.'/wp-json/external/v1/posts/'.$action.'/'.$remoteId;

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
