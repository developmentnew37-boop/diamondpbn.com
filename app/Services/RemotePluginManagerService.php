<?php

namespace App\Services;

use App\Models\Admin\Domain;
use App\Models\Admin\PluginPackage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class RemotePluginManagerService
{
    public function __construct(
        private readonly PluginDeployPayloadBuilder $payloadBuilder,
        private readonly WordPressAgentStatusService $agentStatusService,
    ) {}

    public function baseUrl(string $domain): string
    {
        return BlogrollApiService::baseUrl($domain);
    }

    /**
     * @return array{ok: bool, plugin_manager_supported: bool, plugin_version: ?string, message: string, response_time_ms?: int}
     */
    public function fetchAgentStatus(Domain $domain): array
    {
        $result = $this->agentStatusService->probe($domain->name);
        $domain->persistAgentHealth($result);

        return $result->toLegacyArray();
    }

    /** @return array<string, mixed> */
    public function fetchPluginsInventory(Domain $domain): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins';
        $request = $this->requestWithRetries(
            fn () => $this->request()->get($url, ['api_key' => (string) $domain->api_key]),
            'inventory',
            'GET',
            $url,
            [],
            [(string) $domain->api_key]
        );
        $response = $request['response'];

        if (! $response instanceof Response) {
            return $this->readFailure($request, []);
        }

        $body = $this->json($response);
        if (! $response->successful()) {
            return $this->readFailure($request, [], $this->errorCode($response, $body));
        }

        $raw = $body['data'] ?? $body['plugins'] ?? null;
        if (! is_array($raw)) {
            return $this->readFailure($request, [], 'invalid_response', 'Invalid plugins inventory response');
        }

        $plugins = $this->normalizePluginsInventory($raw);
        if ($plugins === null) {
            return $this->readFailure($request, [], 'invalid_response', 'Invalid plugins inventory response');
        }

        return [
            'ok' => true,
            'plugins' => $plugins,
            'message' => $this->boundedMessage($body['message'] ?? 'OK', [(string) $domain->api_key]),
            'error_code' => null,
            'http_status' => $response->status(),
            'response_time_ms' => $request['response_time_ms'],
            'request_url' => $url,
            'probe_method' => 'inventory',
            'retry_count' => $request['retry_count'],
            'audit' => $request['audit'],
        ];
    }

    /**
     * Flatten agent inventory into a list of {slug, name, version, plugin_file, active}.
     *
     * @param  array<mixed>  $raw
     * @return list<array{slug: string, name: ?string, version: ?string, plugin_file: ?string, active: ?bool}>|null
     */
    public function normalizePluginsInventory(array $raw): ?array
    {
        if (isset($raw['plugins']) && is_array($raw['plugins'])) {
            $raw = $raw['plugins'];
        } elseif (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        }

        $normalized = [];

        foreach ($raw as $key => $plugin) {
            if (! is_array($plugin)) {
                continue;
            }

            // Skip nested wrapper leftovers that are not plugin rows.
            if ($this->looksLikePluginListWrapper($plugin)) {
                continue;
            }

            $pluginFile = $this->stringField($plugin, ['plugin_file', 'file', 'plugin']);
            if (($pluginFile === null || $pluginFile === '') && is_string($key) && str_contains($key, '/')) {
                $pluginFile = str_replace('\\', '/', $key);
            }

            $slug = $this->stringField($plugin, ['slug', 'folder', 'dir']);
            if (($slug === null || $slug === '') && is_string($pluginFile) && $pluginFile !== '') {
                $parts = explode('/', str_replace('\\', '/', $pluginFile));
                $slug = ($parts[0] ?? '') !== '' ? $parts[0] : null;
            }
            if (($slug === null || $slug === '') && is_string($key) && $key !== '' && ! str_contains($key, '/')) {
                $slug = $key;
            }

            $name = $this->stringField($plugin, ['name', 'Name', 'plugin_name', 'title', 'Title']);
            $version = $this->stringField($plugin, ['version', 'Version']);
            $active = $this->boolField($plugin, ['active', 'Active', 'is_active', 'activated']);

            // Require at least one identity field so empty noise rows are dropped.
            if (($slug === null || $slug === '')
                && ($pluginFile === null || $pluginFile === '')
                && ($name === null || $name === '')) {
                continue;
            }

            $normalized[] = [
                'slug' => (string) ($slug ?? ''),
                'name' => $name,
                'version' => $version,
                'plugin_file' => $pluginFile,
                'active' => $active,
            ];
        }

        return array_values($normalized);
    }

    /** @param  array<string, mixed>  $plugin */
    private function looksLikePluginListWrapper(array $plugin): bool
    {
        return (isset($plugin['plugins']) && is_array($plugin['plugins']))
            || (isset($plugin['items']) && is_array($plugin['items']));
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @param  list<string>  $keys
     */
    private function stringField(array $plugin, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $plugin)) {
                continue;
            }
            $value = $plugin[$key];
            if (is_string($value) || is_numeric($value)) {
                $text = trim((string) $value);

                return $text !== '' ? $text : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @param  list<string>  $keys
     */
    private function boolField(array $plugin, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $plugin)) {
                continue;
            }
            $value = $plugin[$key];
            if (is_bool($value)) {
                return $value;
            }
            if (is_int($value) || is_string($value)) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function fetchPluginInfo(Domain $domain, PluginPackage $package): array
    {
        $expectedSlug = $package->expectedSlug();
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/'.rawurlencode($expectedSlug);
        $request = $this->requestWithRetries(
            fn () => $this->request()->get($url, [
                'api_key' => (string) $domain->api_key,
                'target_version' => $package->version,
            ]),
            'single_plugin',
            'GET',
            $url,
            [],
            [(string) $domain->api_key]
        );
        $response = $request['response'];
        $empty = [
            'version' => null,
            'active' => null,
            'plugin_file' => null,
        ];

        if (! $response instanceof Response) {
            return array_merge($this->readFailure($request, $empty), ['found' => null]);
        }

        if ($response->status() === 404) {
            return array_merge($empty, [
                'ok' => true,
                'found' => false,
                'message' => 'Plugin not installed',
                'error_code' => 'plugin_not_found',
                'http_status' => 404,
                'response_time_ms' => $request['response_time_ms'],
                'request_url' => $url,
                'probe_method' => 'single_plugin',
                'retry_count' => $request['retry_count'],
                'audit' => $request['audit'],
            ]);
        }

        $body = $this->json($response);
        if (! $response->successful()) {
            return array_merge(
                $this->readFailure($request, $empty, $this->errorCode($response, $body)),
                ['found' => null]
            );
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;

        return [
            'ok' => true,
            'found' => true,
            'version' => isset($data['version']) ? (string) $data['version'] : null,
            'active' => isset($data['active']) ? (bool) $data['active'] : null,
            'plugin_file' => isset($data['plugin_file']) ? (string) $data['plugin_file'] : null,
            'message' => $this->boundedMessage($body['message'] ?? 'OK', [(string) $domain->api_key]),
            'error_code' => null,
            'http_status' => $response->status(),
            'response_time_ms' => $request['response_time_ms'],
            'request_url' => $url,
            'probe_method' => 'single_plugin',
            'retry_count' => $request['retry_count'],
            'audit' => $request['audit'],
        ];
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function installOrUpdate(
        Domain $domain,
        string $endpoint,
        PluginPackage $package,
        string $downloadUrl,
        bool $activate,
        ?string $pluginFile = null
    ): array {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/'.$endpoint;
        $payload = $this->payloadBuilder->installOrUpdate($package, $downloadUrl, $activate, $pluginFile);

        return $this->postPluginAction($domain, $url, $payload, $endpoint);
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function activate(Domain $domain, PluginPackage $package, ?string $targetVersion = null): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/activate';

        return $this->postPluginAction(
            $domain,
            $url,
            $this->payloadBuilder->activateOrDeactivate($package, $targetVersion),
            'activate'
        );
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function deactivate(Domain $domain, PluginPackage $package, ?string $targetVersion = null): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/deactivate';

        return $this->postPluginAction(
            $domain,
            $url,
            $this->payloadBuilder->activateOrDeactivate($package, $targetVersion),
            'deactivate'
        );
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function deletePlugin(
        Domain $domain,
        PluginPackage $package,
        ?string $pluginFile = null,
        ?string $targetVersion = null
    ): array {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/delete';

        return $this->postPluginAction(
            $domain,
            $url,
            $this->payloadBuilder->delete($package, $pluginFile, $targetVersion),
            'delete'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    private function postPluginAction(Domain $domain, string $url, array $payload, string $action): array
    {
        $payload['api_key'] = (string) $domain->api_key;
        $request = $this->requestWithRetries(
            fn () => $this->request()
                ->withHeaders(['X-External-API-Key' => (string) $domain->api_key])
                ->post($url, $payload),
            'action:'.$action,
            'POST',
            $url,
            $payload,
            [(string) $domain->api_key]
        );
        $response = $request['response'];

        if (! $response instanceof Response) {
            return $this->actionFailure($request);
        }

        $body = $this->json($response);
        if ($body === []) {
            return $this->actionFailure($request, 'invalid_response', 'Invalid response from remote site');
        }

        $success = (bool) ($body['success'] ?? $response->successful());
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        if (($data['action'] ?? null) === 'skipped') {
            $success = true;
        }

        return [
            'success' => $success && $response->successful(),
            'message' => $this->boundedMessage(
                $body['message'] ?? ($success ? 'OK' : 'Request failed'),
                [(string) $domain->api_key]
            ),
            'error_code' => $success ? null : $this->errorCode($response, $body),
            'data' => $this->redactSecrets($this->sanitize($data), [(string) $domain->api_key]),
            'response_time_ms' => $request['response_time_ms'],
            'http_status' => $response->status(),
            'request_url' => $url,
            'probe_method' => 'action:'.$action,
            'retry_count' => $request['retry_count'],
            'audit' => $request['audit'],
        ];
    }

    /** @return array<string, mixed> */
    public function agentStatusAudit(Domain $domain, array $result): array
    {
        $probeMethod = (string) ($result['probe_method'] ?? 'rest');
        $requestUrl = $probeMethod === 'fallback'
            ? $this->baseUrl($domain->name).'/?diamondpbn_status=1'
            : $this->baseUrl($domain->name).'/wp-json/external/v1/status';

        return [
            'probe_method' => $probeMethod,
            'method' => 'GET',
            'request_url' => $requestUrl,
            'http_status' => $result['http_status'] ?? null,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'error_code' => $result['code'] ?? null,
            'message' => $this->boundedMessage($result['message'] ?? '', [(string) $domain->api_key]),
            'response_excerpt' => $this->boundedExcerpt($result['json'] ?? null, [(string) $domain->api_key]),
        ];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->requestTimeout())
            ->connectTimeout($this->connectTimeout())
            ->acceptJson()
            ->withOptions(['verify' => ! (bool) config('plugin_manager.insecure_tls', false)]);
    }

    /**
     * @param  callable(): Response  $send
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $secrets
     * @return array<string, mixed>
     */
    private function requestWithRetries(
        callable $send,
        string $probeMethod,
        string $method,
        string $safeUrl,
        array $payload = [],
        array $secrets = []
    ): array {
        $started = microtime(true);
        $audit = [];
        $response = null;
        $exception = null;
        $maxAttempts = $this->retryAttempts();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $attemptStarted = microtime(true);
            $exception = null;

            try {
                $response = $send();
            } catch (Throwable $e) {
                $response = null;
                $exception = $e;
            }

            $body = $response instanceof Response ? $this->json($response) : [];
            $errorCode = $response instanceof Response
                ? $this->errorCode($response, $body)
                : 'request_failed';
            $audit[] = [
                'attempt' => $attempt,
                'probe_method' => $probeMethod,
                'method' => $method,
                'request_url' => $safeUrl,
                'request_payload' => $this->sanitize($payload),
                'http_status' => $response?->status(),
                'response_time_ms' => $this->elapsedMs($attemptStarted),
                'error_code' => $response?->successful() ? null : $errorCode,
                'message' => $this->boundedMessage(
                    $exception?->getMessage() ?? ($body['message'] ?? ($response?->reason() ?? 'Request failed')),
                    $secrets
                ),
                'response_excerpt' => $this->boundedExcerpt($body, $secrets),
            ];

            if (! $this->shouldRetry($response, $body, $exception) || $attempt === $maxAttempts) {
                break;
            }

            usleep($this->retryDelayMs($response, $attempt) * 1000);
        }

        return [
            'response' => $response,
            'exception' => $exception,
            'response_time_ms' => $this->elapsedMs($started),
            'retry_count' => max(0, count($audit) - 1),
            'audit' => $audit,
            'request_url' => $safeUrl,
            'probe_method' => $probeMethod,
            'secrets' => $secrets,
        ];
    }

    private function shouldRetry(?Response $response, array $body, ?Throwable $exception): bool
    {
        if ($exception !== null) {
            return true;
        }

        $code = $body['code'] ?? null;

        return in_array($response?->status(), [429, 502, 503], true)
            || in_array($code, ['request_failed', 'download_failed'], true);
    }

    private function retryDelayMs(?Response $response, int $attempt): int
    {
        $maximum = max(0, min(1500, (int) config('plugin_manager.http_retry_max_delay_ms', 1000)));
        $retryAfter = $response?->header('Retry-After');

        if (is_string($retryAfter) && ctype_digit(trim($retryAfter))) {
            return min($maximum, ((int) trim($retryAfter)) * 1000);
        }

        if (is_string($retryAfter) && ($retryAt = strtotime($retryAfter)) !== false) {
            return min($maximum, max(0, $retryAt - time()) * 1000);
        }

        return min($maximum, 200 * $attempt);
    }

    /** @param array<string, mixed> $defaults */
    private function readFailure(
        array $request,
        array $defaults,
        ?string $errorCode = null,
        ?string $message = null
    ): array {
        $response = $request['response'];
        $code = $errorCode ?? 'request_failed';

        return array_merge($defaults, [
            'ok' => false,
            'plugins' => $defaults === [] ? [] : ($defaults['plugins'] ?? null),
            'message' => $this->boundedMessage(
                $message ?? $request['exception']?->getMessage() ?? ($response?->reason() ?? 'Request failed'),
                $request['secrets'] ?? []
            ),
            'error_code' => $code,
            'http_status' => $response?->status(),
            'response_time_ms' => $request['response_time_ms'],
            'request_url' => $request['request_url'],
            'probe_method' => $request['probe_method'],
            'retry_count' => $request['retry_count'],
            'audit' => $request['audit'],
        ]);
    }

    private function actionFailure(array $request, ?string $errorCode = null, ?string $message = null): array
    {
        $failure = $this->readFailure($request, [], $errorCode, $message);

        return array_merge($failure, ['success' => false, 'data' => []]);
    }

    private function errorCode(Response $response, array $body): string
    {
        $code = is_string($body['code'] ?? null) && $body['code'] !== ''
            ? (string) $body['code']
            : null;
        $message = is_string($body['message'] ?? null) ? (string) $body['message'] : '';
        $looksNotInstalled = $message !== '' && preg_match('/not\s+installed/i', $message) === 1;

        // Prefer a plugin-missing code when remote says the plugin is absent.
        if ($looksNotInstalled && in_array($code, ['not_found', 'plugin_not_found', null], true)) {
            return 'plugin_not_found';
        }

        if ($code !== null) {
            return $code;
        }

        return match (true) {
            $response->status() === 401 || $response->status() === 403 => 'authentication_failed',
            $response->status() === 404 && $looksNotInstalled => 'plugin_not_found',
            $response->status() === 404 => 'endpoint_not_found',
            $response->status() === 429 => 'rate_limited',
            in_array($response->status(), [502, 503], true) => 'remote_unavailable',
            $response->serverError() => 'remote_server_error',
            default => 'http_error',
        };
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        try {
            $body = $response->json();

            return is_array($body) ? $body : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/api[_-]?key|authorization|signature|token|secret/i', $key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }

            return $clean;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            if (isset($parts['query'])) {
                return strtok($value, '?').'?[REDACTED]';
            }
        }

        return $value;
    }

    /** @param array<int, string> $secrets */
    private function boundedExcerpt(mixed $value, array $secrets = []): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        $encoded = json_encode($this->sanitize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded)
            ? mb_substr((string) $this->redactSecrets($encoded, $secrets), 0, 2000)
            : null;
    }

    /** @param array<int, string> $secrets */
    private function boundedMessage(mixed $message, array $secrets = []): string
    {
        $text = is_scalar($message) ? trim((string) $message) : 'Request failed';

        return mb_substr((string) $this->redactSecrets($text, $secrets), 0, 1000);
    }

    /** @param array<int, string> $secrets */
    private function redactSecrets(mixed $value, array $secrets): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->redactSecrets($item, $secrets), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        foreach (array_filter($secrets, fn ($secret) => $secret !== '') as $secret) {
            $value = str_replace($secret, '[REDACTED]', $value);
            $value = str_replace(urlencode($secret), '[REDACTED]', $value);
        }

        return $value;
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    public function requestTimeout(): int
    {
        return max(30, (int) config('plugin_manager.request_timeout', 180));
    }

    public function connectTimeout(): int
    {
        return max(5, (int) config('plugin_manager.connect_timeout', 20));
    }

    public function retryAttempts(): int
    {
        return max(1, min(3, (int) config('plugin_manager.http_retry_attempts', 2)));
    }

    public function meetsMinAgentVersion(?string $pluginVersion): bool
    {
        if ($pluginVersion === null || $pluginVersion === '') {
            return false;
        }

        $minimum = $this->agentStatusService->minimumVersion();

        return version_compare($pluginVersion, $minimum, '>=');
    }
}
