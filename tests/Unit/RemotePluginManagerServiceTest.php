<?php

namespace Tests\Unit;

use App\Models\Admin\Domain;
use App\Models\Admin\PluginPackage;
use App\Services\RemotePluginManagerService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemotePluginManagerServiceTest extends TestCase
{
    public function test_only_an_actual_single_plugin_404_is_verified_not_found(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'gateway failure'], 503)
            ->push(['message' => 'gateway failure'], 503)
            ->push(['message' => 'gateway failure'], 503)
            ->push(['message' => 'missing'], 404);

        config()->set('plugin_manager.http_retry_max_delay_ms', 0);
        $service = app(RemotePluginManagerService::class);
        $domain = new Domain(['name' => 'plugins.example', 'api_key' => 'top-secret']);
        $package = $this->package();

        $unverified = $service->fetchPluginInfo($domain, $package);
        $missing = $service->fetchPluginInfo($domain, $package);

        $this->assertFalse($unverified['ok']);
        $this->assertNull($unverified['found']);
        $this->assertSame(503, $unverified['http_status']);
        $this->assertTrue($missing['ok']);
        $this->assertFalse($missing['found']);
        $this->assertSame('plugin_not_found', $missing['error_code']);
    }

    public function test_action_retries_transient_failure_and_returns_redacted_audit(): void
    {
        Http::fakeSequence()
            ->push(['success' => false, 'code' => 'download_failed', 'api_key' => 'top-secret'], 503)
            ->push([
                'success' => true,
                'message' => 'Installed',
                'data' => ['action' => 'installed', 'plugin_file' => 'sample/sample.php'],
            ]);

        config()->set('plugin_manager.http_retry_max_delay_ms', 0);
        $result = app(RemotePluginManagerService::class)->installOrUpdate(
            new Domain(['name' => 'plugins.example', 'api_key' => 'top-secret']),
            'install',
            $this->package(),
            'https://dashboard.example/download?signature=signed-secret',
            true,
            'sample/sample.php'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['retry_count']);
        $this->assertCount(2, $result['audit']);
        $this->assertStringNotContainsString('top-secret', json_encode($result['audit']));
        $this->assertStringNotContainsString('signed-secret', json_encode($result['audit']));

        Http::assertSent(function (Request $request) {
            return $request['plugin_file'] === 'sample/sample.php'
                && $request['api_key'] === 'top-secret';
        });
    }

    public function test_successfully_fetched_empty_inventory_is_explicit(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);

        $result = app(RemotePluginManagerService::class)->fetchPluginsInventory(
            new Domain(['name' => 'plugins.example', 'api_key' => 'top-secret'])
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['plugins']);
        $this->assertSame(200, $result['http_status']);
        $this->assertStringNotContainsString('api_key', $result['request_url']);
    }

    public function test_inventory_normalizes_keyed_plugin_file_map_and_wp_headers(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => [
                'custom_blog/plugin.php' => [
                    'Name' => 'Custom Blog roll Manager',
                    'Version' => '1.6',
                    'Active' => true,
                ],
            ],
        ])]);

        $result = app(RemotePluginManagerService::class)->fetchPluginsInventory(
            new Domain(['name' => 'plugins.example', 'api_key' => 'top-secret'])
        );

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['plugins']);
        $this->assertSame('custom_blog', $result['plugins'][0]['slug']);
        $this->assertSame('custom_blog/plugin.php', $result['plugins'][0]['plugin_file']);
        $this->assertSame('Custom Blog roll Manager', $result['plugins'][0]['name']);
        $this->assertSame('1.6', $result['plugins'][0]['version']);
        $this->assertTrue($result['plugins'][0]['active']);
    }

    public function test_inventory_normalizes_nested_data_plugins_list(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => [
                'plugins' => [[
                    'slug' => 'sample',
                    'version' => '1.0.0',
                    'plugin_file' => 'sample/sample.php',
                ]],
            ],
        ])]);

        $result = app(RemotePluginManagerService::class)->fetchPluginsInventory(
            new Domain(['name' => 'plugins.example', 'api_key' => 'top-secret'])
        );

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['plugins']);
        $this->assertSame('sample', $result['plugins'][0]['slug']);
    }

    public function test_fallback_status_audit_records_the_endpoint_actually_used(): void
    {
        $audit = app(RemotePluginManagerService::class)->agentStatusAudit(
            new Domain(['name' => 'plugins.example', 'api_key' => 'top-secret']),
            [
                'probe_method' => 'fallback',
                'http_status' => 200,
                'message' => 'Connected',
                'json' => ['status' => true],
            ]
        );

        $this->assertSame('fallback', $audit['probe_method']);
        $this->assertSame('https://plugins.example/?diamondpbn_status=1', $audit['request_url']);
        $this->assertStringNotContainsString('top-secret', json_encode($audit));
    }

    private function package(): PluginPackage
    {
        return new PluginPackage([
            'slug' => 'sample-version-1.2.3',
            'expected_slug' => 'sample',
            'version' => '1.2.3',
            'checksum_sha256' => str_repeat('a', 64),
        ]);
    }
}
