<?php

namespace App\Services;

use App\Data\AgentStatusResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WordPressAgentStatusService
{
    public const MINIMUM_AGENT_VERSION = '8.1.5';

    public function probe(string $domainUrl): AgentStatusResult
    {
        $started = microtime(true);

        try {
            $primaryResponse = $this->request()->get($this->primaryUrl($domainUrl));
            $primary = $this->interpret($primaryResponse, 'rest', $this->elapsedMs($started));
        } catch (\Throwable $e) {
            return $this->connectionFailure($e, 'rest', $this->elapsedMs($started));
        }

        if (! $this->shouldTryFallback($primaryResponse, $primary)) {
            return $primary;
        }

        $fallbackStarted = microtime(true);

        try {
            $fallbackResponse = $this->request()->get($this->fallbackUrl($domainUrl));
            $fallback = $this->interpret($fallbackResponse, 'fallback', $this->elapsedMs($fallbackStarted));
        } catch (\Throwable $e) {
            $fallback = $this->connectionFailure($e, 'fallback', $this->elapsedMs($fallbackStarted));
        }

        return $this->preferFallback($primary, $fallback);
    }

    /**
     * Authenticated POST /status/check — verifies site reachability and API key validity.
     */
    public function probeAuthenticated(
        string $domainUrl,
        string $apiKey,
        ?int $timeout = null,
        ?int $connectTimeout = null
    ): AgentStatusResult {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            return new AgentStatusResult(
                false,
                'missing_api_key',
                'No API key is stored for this domain. Authenticated status check was skipped.',
                probeMethod: 'auth_rest',
            );
        }

        $started = microtime(true);

        try {
            $response = $this->request($timeout, $connectTimeout)
                ->withHeaders([
                    'X-External-API-Key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->authenticatedCheckUrl($domainUrl), [
                    'api_key' => $apiKey,
                ]);

            return $this->interpretAuthenticated($response, 'auth_rest', $this->elapsedMs($started));
        } catch (\Throwable $e) {
            return $this->connectionFailure($e, 'auth_rest', $this->elapsedMs($started));
        }
    }

    /**
     * Public GET probe first; if it fails and an API key is available, try POST /status/check.
     * Useful when firewalls intercept unauthenticated GET /status.
     */
    public function probeWithAuthFallback(string $domainUrl, ?string $apiKey = null): AgentStatusResult
    {
        $public = $this->probe($domainUrl);

        if ($public->ok) {
            return $public;
        }

        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            return $public;
        }

        $authenticated = $this->probeAuthenticated($domainUrl, $apiKey);

        return $authenticated->ok ? $authenticated : $this->preferAuthenticatedDiagnosis($public, $authenticated);
    }

    /**
     * Probe many domains concurrently. Keys from the input are preserved.
     *
     * @param  array<int|string, string>  $domains
     * @return array<int|string, AgentStatusResult>
     */
    public function probeMany(array $domains, ?int $timeout = null, ?int $connectTimeout = null): array
    {
        if ($domains === []) {
            return [];
        }

        $started = microtime(true);
        $responses = Http::pool(function (Pool $pool) use ($domains, $timeout, $connectTimeout) {
            foreach ($domains as $key => $domain) {
                $this->configurePoolRequest($pool->as((string) $key), $timeout, $connectTimeout)
                    ->get($this->primaryUrl($domain));
            }
        });

        $results = [];
        $fallbackDomains = [];

        foreach ($domains as $key => $domain) {
            $response = $responses[(string) $key] ?? null;

            if ($response instanceof Response) {
                $result = $this->interpret($response, 'rest', $this->responseTimeMs($response, $started));
                $results[$key] = $result;

                if ($this->shouldTryFallback($response, $result)) {
                    $fallbackDomains[$key] = $domain;
                }

                continue;
            }

            $error = $response instanceof \Throwable
                ? $response
                : new \RuntimeException('No response received (timeout or connection error)');
            $results[$key] = $this->connectionFailure($error, 'rest', $this->elapsedMs($started));
        }

        if ($fallbackDomains === []) {
            return $results;
        }

        $fallbackStarted = microtime(true);
        $fallbackResponses = Http::pool(function (Pool $pool) use ($fallbackDomains, $timeout, $connectTimeout) {
            foreach ($fallbackDomains as $key => $domain) {
                $this->configurePoolRequest($pool->as((string) $key), $timeout, $connectTimeout)
                    ->get($this->fallbackUrl($domain));
            }
        });

        foreach ($fallbackDomains as $key => $domain) {
            $response = $fallbackResponses[(string) $key] ?? null;
            $fallback = $response instanceof Response
                ? $this->interpret($response, 'fallback', $this->responseTimeMs($response, $fallbackStarted))
                : $this->connectionFailure(
                    $response instanceof \Throwable ? $response : new \RuntimeException('No fallback response received'),
                    'fallback',
                    $this->elapsedMs($fallbackStarted)
                );

            $results[$key] = $this->preferFallback($results[$key], $fallback);
        }

        return $results;
    }

    public function primaryUrl(string $domain): string
    {
        return $this->baseUrl($domain).'/wp-json/external/v1/status';
    }

    public function fallbackUrl(string $domain): string
    {
        return $this->baseUrl($domain).'/?diamondpbn_status=1';
    }

    public function authenticatedCheckUrl(string $domain): string
    {
        return $this->baseUrl($domain).'/wp-json/external/v1/status/check';
    }

    /**
     * Probe many inventory domains with optional authenticated POST when public GET fails.
     *
     * @param  array<int|string, array{domain: string, api_key?: string|null}>  $targets
     * @return array<int|string, AgentStatusResult>
     */
    public function probeManyWithAuthFallback(array $targets, ?int $timeout = null, ?int $connectTimeout = null): array
    {
        if ($targets === []) {
            return [];
        }

        $domains = [];
        foreach ($targets as $key => $target) {
            $domains[$key] = is_array($target) ? (string) ($target['domain'] ?? '') : (string) $target;
        }

        $results = $this->probeMany($domains, $timeout, $connectTimeout);

        foreach ($targets as $key => $target) {
            $public = $results[$key] ?? null;

            if ($public?->ok) {
                continue;
            }

            $apiKey = is_array($target) ? trim((string) ($target['api_key'] ?? '')) : '';

            if ($apiKey === '') {
                continue;
            }

            $authenticated = $this->probeAuthenticated(
                (string) $domains[$key],
                $apiKey,
                $timeout,
                $connectTimeout
            );
            $results[$key] = $authenticated->ok || $public === null
                ? $authenticated
                : $this->preferAuthenticatedDiagnosis($public, $authenticated);
        }

        return $results;
    }

    public function looksLikeHtml(string $body): bool
    {
        $sample = strtolower(substr(ltrim($body), 0, 4096));

        return str_starts_with($sample, '<!doctype')
            || str_starts_with($sample, '<html')
            || str_contains($sample, '<body')
            || str_contains($sample, 'wordfence')
            || str_contains($sample, 'access denied')
            || str_contains($sample, 'cloudflare');
    }

    private function looksLikeCloudflareBlock(string $body): bool
    {
        $sample = strtolower(trim($body));

        return str_contains($sample, 'error code: 1010')
            || str_contains($sample, 'cloudflare ray id');
    }

    public function minimumVersion(): string
    {
        return (string) config('plugin_manager.min_agent_version', self::MINIMUM_AGENT_VERSION);
    }

    private function request(?int $timeout = null, ?int $connectTimeout = null): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($timeout ?? $this->requestTimeout())
            ->connectTimeout($connectTimeout ?? $this->connectTimeout())
            ->withOptions(['verify' => ! (bool) config('plugin_manager.insecure_tls', false)])
            ->withHeaders($this->headers());
    }

    private function configurePoolRequest(
        mixed $request,
        ?int $timeout,
        ?int $connectTimeout
    ): mixed {
        return $request
            ->timeout($timeout ?? $this->requestTimeout())
            ->connectTimeout($connectTimeout ?? $this->connectTimeout())
            ->withOptions(['verify' => ! (bool) config('plugin_manager.insecure_tls', false)])
            ->withHeaders($this->headers());
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    private function interpretAuthenticated(Response $response, string $probeMethod, int $responseTimeMs): AgentStatusResult
    {
        $httpStatus = $response->status();
        $rawBody = $response->body();
        $json = $this->json($response);
        $message = $this->message($json);
        $code = is_string($json['code'] ?? null) ? (string) $json['code'] : null;

        if ($httpStatus === 403 && ($this->looksLikeHtml($rawBody) || $this->looksLikeCloudflareBlock($rawBody))) {
            return new AgentStatusResult(
                false,
                'firewall_blocked',
                'HTTP 403: Firewall blocked the authenticated status check. Whitelist the dashboard server IP for POST /wp-json/external/v1/status/check.',
                probeMethod: $probeMethod,
                httpStatus: $httpStatus,
                responseTimeMs: $responseTimeMs,
                json: $json,
            );
        }

        if ($httpStatus === 403) {
            $authCode = match ($code) {
                'no_key' => 'missing_api_key',
                'bad_key' => 'invalid_api_key',
                default => 'invalid_api_key',
            };

            return new AgentStatusResult(
                false,
                $authCode,
                $message ?? ($authCode === 'missing_api_key'
                    ? 'API key was not accepted by the remote site (missing key).'
                    : 'API key was rejected by the remote site (invalid key).'),
                probeMethod: $probeMethod,
                httpStatus: $httpStatus,
                responseTimeMs: $responseTimeMs,
                json: $json,
            );
        }

        return $this->interpret($response, $probeMethod, $responseTimeMs);
    }

    private function interpret(Response $response, string $probeMethod, int $responseTimeMs): AgentStatusResult
    {
        $httpStatus = $response->status();
        $rawBody = $response->body();
        $json = $this->json($response);
        $message = $this->message($json);

        if ($httpStatus === 403 && ($this->looksLikeHtml($rawBody) || $this->looksLikeCloudflareBlock($rawBody))) {
            return new AgentStatusResult(
                false,
                'firewall_blocked',
                'HTTP 403: Firewall blocked the dashboard. Whitelist the dashboard server IP or allow both status endpoints.',
                probeMethod: $probeMethod,
                httpStatus: $httpStatus,
                responseTimeMs: $responseTimeMs,
                json: $json,
            );
        }

        if (! $response->successful()) {
            $code = match (true) {
                $httpStatus === 404 => 'agent_not_found',
                $httpStatus >= 500 => 'domain_offline',
                default => 'invalid_status_response',
            };

            return new AgentStatusResult(
                false,
                $code,
                $this->siteErrorMessage($httpStatus, $message, $rawBody),
                probeMethod: $probeMethod,
                httpStatus: $httpStatus,
                responseTimeMs: $responseTimeMs,
                json: $json,
            );
        }

        if ($json === null) {
            return new AgentStatusResult(
                false,
                'invalid_status_response',
                'Invalid JSON from status endpoint',
                probeMethod: $probeMethod,
                httpStatus: $httpStatus,
                responseTimeMs: $responseTimeMs,
            );
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $status = $json['status'] ?? ($data['status'] ?? null);

        if (! $this->truthy($status)) {
            return new AgentStatusResult(
                false,
                $status === false || $status === 0 ? 'agent_not_found' : 'invalid_status_response',
                $message ?? 'Plugin status response did not report an online agent',
                probeMethod: $probeMethod,
                httpStatus: $httpStatus,
                responseTimeMs: $responseTimeMs,
                json: $json,
            );
        }

        $versionValue = $json['plugin_version'] ?? ($data['plugin_version'] ?? null);
        $version = is_scalar($versionValue) && trim((string) $versionValue) !== ''
            ? trim((string) $versionValue)
            : null;
        $supportValue = $json['plugin_manager_supported'] ?? ($data['plugin_manager_supported'] ?? false);
        $advertisesSupport = filter_var($supportValue, FILTER_VALIDATE_BOOLEAN);
        $meetsMinimum = $version !== null && version_compare($version, $this->minimumVersion(), '>=');
        $supported = $advertisesSupport && $meetsMinimum;

        $code = match (true) {
            $version !== null && ! $meetsMinimum => 'agent_outdated',
            ! $advertisesSupport => 'agent_unsupported',
            default => 'online',
        };

        return new AgentStatusResult(
            true,
            $code,
            $message ?? 'Connected',
            $supported,
            $version,
            $probeMethod,
            $httpStatus,
            $responseTimeMs,
            $json,
        );
    }

    private function shouldTryFallback(Response $response, AgentStatusResult $result): bool
    {
        return ! $result->ok && $this->looksLikeHtml($response->body());
    }

    private function preferFallback(AgentStatusResult $primary, AgentStatusResult $fallback): AgentStatusResult
    {
        if ($fallback->ok) {
            return $fallback;
        }

        // An opportunistic fallback must not hide the useful primary diagnosis.
        if ($primary->code === 'firewall_blocked') {
            return $primary;
        }

        if ($this->isHttpServerError($primary)) {
            return $primary;
        }

        return $fallback->code === 'domain_offline' ? $primary : $fallback;
    }

    private function preferAuthenticatedDiagnosis(AgentStatusResult $public, AgentStatusResult $authenticated): AgentStatusResult
    {
        // Prefer API-key diagnosis when the public probe was blocked or inconclusive.
        if (in_array($authenticated->code, ['invalid_api_key', 'missing_api_key'], true)) {
            return $authenticated;
        }

        if ($this->isHttpServerError($public) && ! $this->isHttpServerError($authenticated)) {
            return $public;
        }

        if (in_array($public->code, ['firewall_blocked', 'invalid_status_response', 'agent_not_found'], true)
            && $authenticated->code !== 'domain_offline') {
            return $authenticated;
        }

        return $public;
    }

    private function connectionFailure(\Throwable $e, string $probeMethod, int $responseTimeMs): AgentStatusResult
    {
        return new AgentStatusResult(
            false,
            'domain_offline',
            'Request failed: '.$e->getMessage(),
            probeMethod: $probeMethod,
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function json(Response $response): ?array
    {
        try {
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function isHttpServerError(AgentStatusResult $result): bool
    {
        return $result->httpStatus !== null && $result->httpStatus >= 500;
    }

    private function siteErrorMessage(int $httpStatus, ?string $jsonMessage, string $rawBody): string
    {
        $detail = $this->usefulErrorText($jsonMessage) ?? $this->htmlErrorText($rawBody) ?? $this->httpReason($httpStatus);
        $detail = preg_replace('/^HTTP\s+'.$httpStatus.'\s*[:\-]?\s*/i', '', $detail) ?? $detail;
        $detail = preg_replace('/^'.$httpStatus.'\s*[:\-]?\s*/', '', $detail) ?? $detail;
        $detail = trim($detail);

        if ($detail === '') {
            $detail = $this->httpReason($httpStatus);
        }

        return 'HTTP '.$httpStatus.': '.$detail;
    }

    private function htmlErrorText(string $body): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $match) === 1) {
            $title = $this->usefulErrorText(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== null) {
                return $title;
            }
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $body, $match) === 1) {
            return $this->usefulErrorText(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    private function usefulErrorText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if ($text === '') {
            return null;
        }

        $lower = strtolower($text);
        if (in_array($lower, ['error', 'just a moment...', 'just a moment'], true) || str_contains($lower, 'just a moment')) {
            return null;
        }

        if (! str_contains($text, ' ') && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $text) === 1) {
            return null;
        }

        if (mb_strlen($text) > 180) {
            return mb_substr($text, 0, 179).'…';
        }

        return $text;
    }

    private function httpReason(int $httpStatus): string
    {
        return match ($httpStatus) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            507 => 'Insufficient Storage',
            508 => 'Resource Limit Is Reached',
            509 => 'Bandwidth Limit Exceeded',
            520 => 'Web Server Returned an Unknown Error',
            521 => 'Web Server Is Down',
            522 => 'Connection Timed Out',
            523 => 'Origin Is Unreachable',
            524 => 'A Timeout Occurred',
            default => 'Error',
        };
    }

    private function message(?array $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $message = $json['message'] ?? ($data['message'] ?? null);

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    private function truthy(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        return is_string($value)
            && in_array(strtolower(trim($value)), ['true', '1', 'yes', 'ok', 'online', 'connected'], true);
    }

    private function baseUrl(string $domain): string
    {
        $normalized = normalizeDomainName($domain);

        return 'https://'.$normalized;
    }

    private function requestTimeout(): int
    {
        return max(5, (int) config(
            'domain_status_checker.request_timeout',
            config('plugin_manager.status_timeout', 15)
        ));
    }

    private function connectTimeout(): int
    {
        return max(2, (int) config(
            'domain_status_checker.connect_timeout',
            config('plugin_manager.status_connect_timeout', 10)
        ));
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function responseTimeMs(Response $response, float $started): int
    {
        if ($response->transferStats !== null) {
            return (int) round($response->transferStats->getTransferTime() * 1000);
        }

        return $this->elapsedMs($started);
    }
}
