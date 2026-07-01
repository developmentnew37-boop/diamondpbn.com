<?php

namespace App\Services;

use App\Models\Admin\Domain;
use App\Models\Admin\PluginPackage;
use Illuminate\Support\Facades\Http;

class RemotePluginManagerService
{
    public function __construct(
        private readonly PluginDeployPayloadBuilder $payloadBuilder
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
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/status';

        $started = microtime(true);

        try {
            $response = Http::withoutVerifying()
                ->timeout(min(30, $this->requestTimeout()))
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->get($url);

            $ms = (int) round((microtime(true) - $started) * 1000);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'plugin_manager_supported' => false,
                    'plugin_version' => null,
                    'message' => 'HTTP '.$response->status(),
                    'response_time_ms' => $ms,
                ];
            }

            $body = $response->json();
            if (! is_array($body)) {
                return [
                    'ok' => false,
                    'plugin_manager_supported' => false,
                    'plugin_version' => null,
                    'message' => 'Invalid JSON from status endpoint',
                    'response_time_ms' => $ms,
                ];
            }

            $supported = $body['plugin_manager_supported'] ?? ($body['data']['plugin_manager_supported'] ?? false);
            $version = $body['plugin_version'] ?? ($body['data']['plugin_version'] ?? null);

            $pluginVersion = is_string($version) ? $version : null;
            $pluginManagerSupported = filter_var($supported, FILTER_VALIDATE_BOOLEAN)
                && $this->meetsMinAgentVersion($pluginVersion);

            return [
                'ok' => true,
                'plugin_manager_supported' => $pluginManagerSupported,
                'plugin_version' => $pluginVersion,
                'message' => (string) ($body['message'] ?? 'Connected'),
                'response_time_ms' => $ms,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'plugin_manager_supported' => false,
                'plugin_version' => null,
                'message' => $e->getMessage(),
                'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    /**
     * @return array{ok: bool, plugins: array<int, array<string, mixed>>, message: string}
     */
    public function fetchPluginsInventory(Domain $domain): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins'
            .'?api_key='.urlencode((string) $domain->api_key);

        try {
            $response = Http::withoutVerifying()
                ->timeout($this->requestTimeout())
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'plugins' => [],
                    'message' => 'HTTP '.$response->status(),
                ];
            }

            $body = $response->json();
            $plugins = $body['data'] ?? $body['plugins'] ?? [];

            if (! is_array($plugins)) {
                return [
                    'ok' => false,
                    'plugins' => [],
                    'message' => 'Invalid plugins inventory response',
                ];
            }

            /** @var array<int, array<string, mixed>> $normalized */
            $normalized = array_values(array_filter($plugins, 'is_array'));

            return [
                'ok' => true,
                'plugins' => $normalized,
                'message' => (string) ($body['message'] ?? 'OK'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'plugins' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{found: bool, version: ?string, active: ?bool, plugin_file: ?string, message: string}
     */
    public function fetchPluginInfo(Domain $domain, PluginPackage $package): array
    {
        $expectedSlug = $package->expectedSlug();
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/'.$expectedSlug
            .'?api_key='.urlencode((string) $domain->api_key)
            .'&target_version='.urlencode($package->version);

        try {
            $response = Http::withoutVerifying()
                ->timeout($this->requestTimeout())
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->get($url);

            if ($response->status() === 404) {
                return [
                    'found' => false,
                    'version' => null,
                    'active' => null,
                    'plugin_file' => null,
                    'message' => 'Plugin not installed',
                ];
            }

            if (! $response->successful()) {
                return [
                    'found' => false,
                    'version' => null,
                    'active' => null,
                    'plugin_file' => null,
                    'message' => 'HTTP '.$response->status(),
                ];
            }

            $body = $response->json();
            $data = is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);

            return [
                'found' => true,
                'version' => isset($data['version']) ? (string) $data['version'] : null,
                'active' => isset($data['active']) ? (bool) $data['active'] : null,
                'plugin_file' => isset($data['plugin_file']) ? (string) $data['plugin_file'] : null,
                'message' => (string) ($body['message'] ?? 'OK'),
            ];
        } catch (\Throwable $e) {
            return [
                'found' => false,
                'version' => null,
                'active' => null,
                'plugin_file' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function installOrUpdate(
        Domain $domain,
        string $endpoint,
        PluginPackage $package,
        string $downloadUrl,
        bool $activate
    ): array {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/'.$endpoint;
        $payload = $this->payloadBuilder->installOrUpdate($package, $downloadUrl, $activate);

        return $this->postPluginAction($domain, $url, $payload);
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function activate(Domain $domain, PluginPackage $package): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/activate';

        return $this->postPluginAction($domain, $url, $this->payloadBuilder->activateOrDeactivate($package));
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function deactivate(Domain $domain, PluginPackage $package): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/deactivate';

        return $this->postPluginAction($domain, $url, $this->payloadBuilder->activateOrDeactivate($package));
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    public function deletePlugin(Domain $domain, PluginPackage $package, ?string $pluginFile = null): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/delete';

        return $this->postPluginAction($domain, $url, $this->payloadBuilder->delete($package, $pluginFile));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int, http_status: int}
     */
    private function postPluginAction(Domain $domain, string $url, array $payload): array
    {
        $payload['api_key'] = (string) $domain->api_key;
        $started = microtime(true);

        try {
            $response = Http::withoutVerifying()
                ->timeout($this->requestTimeout())
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->withHeaders([
                    'X-External-API-Key' => (string) $domain->api_key,
                ])
                ->post($url, $payload);

            $ms = (int) round((microtime(true) - $started) * 1000);
            $body = $response->json();

            if (! is_array($body)) {
                return [
                    'success' => false,
                    'message' => 'Invalid response from remote site',
                    'error_code' => 'invalid_response',
                    'data' => [],
                    'response_time_ms' => $ms,
                    'http_status' => $response->status(),
                ];
            }

            $success = (bool) ($body['success'] ?? $response->successful());
            $errorCode = $body['code'] ?? null;
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];

            $action = $data['action'] ?? null;
            if ($action === 'skipped') {
                $success = true;
            }

            return [
                'success' => $success && $response->successful(),
                'message' => (string) ($body['message'] ?? ($success ? 'OK' : 'Request failed')),
                'error_code' => is_string($errorCode) ? $errorCode : null,
                'data' => $data,
                'response_time_ms' => $ms,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'request_failed',
                'data' => [],
                'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
                'http_status' => 0,
            ];
        }
    }

    public function requestTimeout(): int
    {
        return max(30, (int) config('plugin_manager.request_timeout', 180));
    }

    public function connectTimeout(): int
    {
        return max(5, (int) config('plugin_manager.connect_timeout', 20));
    }

    public function meetsMinAgentVersion(?string $pluginVersion): bool
    {
        if ($pluginVersion === null || $pluginVersion === '') {
            return false;
        }

        $minimum = (string) config('plugin_manager.min_agent_version', '8.1.5');

        return version_compare($pluginVersion, $minimum, '>=');
    }
}
