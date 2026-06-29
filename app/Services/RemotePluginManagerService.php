<?php

namespace App\Services;

use App\Models\Admin\Domain;
use Illuminate\Support\Facades\Http;

class RemotePluginManagerService
{
    public function baseUrl(string $domain): string
    {
        return BlogrollApiService::baseUrl($domain);
    }

    /**
     * @return array{ok: bool, plugin_manager_supported: bool, plugin_version: ?string, message: string}
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

            return [
                'ok' => true,
                'plugin_manager_supported' => filter_var($supported, FILTER_VALIDATE_BOOLEAN),
                'plugin_version' => is_string($version) ? $version : null,
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
     * @return array{found: bool, version: ?string, active: ?bool, message: string}
     */
    public function fetchPluginInfo(Domain $domain, string $slug): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/'.$slug
            .'?api_key='.urlencode((string) $domain->api_key);

        try {
            $response = Http::withoutVerifying()
                ->timeout($this->requestTimeout())
                ->connectTimeout($this->connectTimeout())
                ->acceptJson()
                ->get($url);

            if ($response->status() === 404) {
                return ['found' => false, 'version' => null, 'active' => null, 'message' => 'Plugin not installed'];
            }

            if (! $response->successful()) {
                return ['found' => false, 'version' => null, 'active' => null, 'message' => 'HTTP '.$response->status()];
            }

            $body = $response->json();
            $data = is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);

            return [
                'found' => true,
                'version' => isset($data['version']) ? (string) $data['version'] : null,
                'active' => isset($data['active']) ? (bool) $data['active'] : null,
                'message' => (string) ($body['message'] ?? 'OK'),
            ];
        } catch (\Throwable $e) {
            return ['found' => false, 'version' => null, 'active' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>}
     */
    public function installOrUpdate(
        Domain $domain,
        string $endpoint,
        string $slug,
        string $downloadUrl,
        string $checksum,
        bool $activate
    ): array {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/'.$endpoint;

        return $this->postPluginAction($domain, $url, [
            'delivery' => 'url',
            'download_url' => $downloadUrl,
            'expected_slug' => $slug,
            'slug' => $slug,
            'expected_checksum_sha256' => $checksum,
            'activate' => $activate,
        ]);
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>}
     */
    public function activate(Domain $domain, string $slug): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/activate';

        return $this->postPluginAction($domain, $url, ['slug' => $slug]);
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>}
     */
    public function deactivate(Domain $domain, string $slug): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/deactivate';

        return $this->postPluginAction($domain, $url, ['slug' => $slug]);
    }

    /**
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>}
     */
    public function deletePlugin(Domain $domain, string $slug): array
    {
        $url = $this->baseUrl($domain->name).'/wp-json/external/v1/plugins/delete';

        return $this->postPluginAction($domain, $url, ['slug' => $slug]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, error_code: ?string, data: array<string, mixed>, response_time_ms: int}
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
                ];
            }

            $success = (bool) ($body['success'] ?? $response->successful());
            $errorCode = $body['code'] ?? null;
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];

            return [
                'success' => $success && $response->successful(),
                'message' => (string) ($body['message'] ?? ($success ? 'OK' : 'Request failed')),
                'error_code' => is_string($errorCode) ? $errorCode : null,
                'data' => $data,
                'response_time_ms' => $ms,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'request_failed',
                'data' => [],
                'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
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
}
