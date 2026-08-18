<?php

namespace Tests\Unit;

use App\Models\Admin\Domain;
use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginDeploymentItem;
use App\Models\Admin\PluginPackage;
use App\Services\DomainStatusCheckerService;
use App\Services\PluginDeploymentService;
use App\Services\PluginDeployPreflightService;
use App\Services\PluginPackageService;
use App\Services\RemotePluginManagerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PluginDeploymentHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key');
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->timestamps();
        });
        Schema::create('plugin_deployment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plugin_deployment_id');
            $table->string('domain');
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('item_status')->default('processing');
            $table->string('operation_result')->nullable();
            $table->string('version_before')->nullable();
            $table->string('version_after')->nullable();
            $table->string('plugin_file')->nullable();
            $table->string('resolved_via')->nullable();
            $table->string('error_code')->nullable();
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('request_url')->nullable();
            $table->string('probe_method')->nullable();
            $table->json('audit_trail')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('plugin_deployment_items');
        Schema::dropIfExists('domains');

        parent::tearDown();
    }

    public function test_required_inventory_failure_is_fail_closed_without_mutation(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [$domain, $item, $deployment, $package] = $this->records('install');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => false,
            'plugins' => [],
            'message' => 'Gateway unavailable',
            'error_code' => 'remote_unavailable',
            'http_status' => 503,
            'response_time_ms' => 12,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 2,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 503]],
        ]);
        $remote->shouldNotReceive('installOrUpdate');
        $remote->shouldNotReceive('activate');
        $remote->shouldNotReceive('deactivate');
        $remote->shouldNotReceive('deletePlugin');

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('failed', $item->item_status);
        $this->assertSame('inventory_unavailable', $item->error_code);
        $this->assertSame(503, $item->http_status);
        $this->assertSame(2, $item->retry_count);
        $this->assertNotEmpty($item->audit_trail);
        $this->assertSame($domain->id, $item->domain_id);
    }

    public function test_update_not_found_is_corrected_to_install(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('update');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'sample',
                'version' => '1.0.0',
                'plugin_file' => 'sample/sample.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint, $sentPackage, $url, $activate, $pluginFile) => $endpoint === 'update'
                && $sentPackage === $package
                && $pluginFile === 'sample/sample.php')
            ->andReturn($this->actionResult(false, 'plugin_not_found', 'action:update'));
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'install')
            ->andReturn($this->actionResult(true, null, 'action:install'));

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('installed', $item->operation_result);
        $this->assertCount(4, $item->audit_trail);
    }

    public function test_install_intent_recovers_when_update_returns_not_found_code(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('install');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'sample',
                'version' => '1.0.0',
                'plugin_file' => 'sample/sample.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'update')
            ->andReturn([
                ...$this->actionResult(false, 'not_found', 'action:update'),
                'message' => 'Plugin is not installed.',
            ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'install')
            ->andReturn($this->actionResult(true, null, 'action:install'));

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('installed', $item->operation_result);
    }

    public function test_install_intent_recovers_when_update_returns_bare_404_endpoint_not_found(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('install');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'sample',
                'version' => '1.1.5',
                'plugin_file' => 'sample/sample.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'update')
            ->andReturn([
                ...$this->actionResult(false, 'endpoint_not_found', 'action:update'),
                'message' => 'Plugin is not installed.',
            ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'install')
            ->andReturn($this->actionResult(true, null, 'action:install'));

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('installed', $item->operation_result);
    }

    public function test_verified_empty_inventory_allows_update_intent_to_install(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('update');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'install')
            ->andReturn($this->actionResult(true, null, 'action:install'));

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('installed', $item->operation_result);
    }

    public function test_install_already_present_is_corrected_to_update_once(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('install');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'install')
            ->andReturn($this->actionResult(false, 'plugin_already_installed', 'action:install'));
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint) => $endpoint === 'update')
            ->andReturn([
                ...$this->actionResult(true, null, 'action:update'),
                'data' => [
                    'action' => 'updated',
                    'new_version' => '1.2.3',
                    'plugin_file' => 'sample/sample.php',
                ],
            ]);

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('updated', $item->operation_result);
    }

    public function test_ambiguous_operation_retries_with_version_matched_plugin_file_and_keeps_audit(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('install');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint, $sentPackage, $url, $activate, $pluginFile) => $endpoint === 'install'
                && $pluginFile === null)
            ->ordered()
            ->andReturn([
                ...$this->actionResult(false, 'ambiguous_plugin', 'action:install'),
                'data' => [
                    'candidates' => [
                        ['version' => '1.0.0', 'plugin_file' => 'sample/old.php'],
                        ['version' => '1.2.3', 'plugin_file' => 'sample/sample.php'],
                    ],
                ],
            ]);
        $remote->shouldReceive('installOrUpdate')
            ->once()
            ->withArgs(fn ($domain, $endpoint, $sentPackage, $url, $activate, $pluginFile) => $endpoint === 'install'
                && $pluginFile === 'sample/sample.php')
            ->ordered()
            ->andReturn($this->actionResult(true, null, 'action:install'));

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('sample/sample.php', $item->plugin_file);
        $this->assertCount(4, $item->audit_trail);
    }

    public function test_delete_uses_remote_inventory_version_and_plugin_file(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('delete');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'sample',
                'version' => '1.1.5',
                'plugin_file' => 'sample/sample.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('deletePlugin')
            ->once()
            ->withArgs(fn ($domain, $sentPackage, $pluginFile, $targetVersion) => $sentPackage === $package
                && $pluginFile === 'sample/sample.php'
                && $targetVersion === '1.1.5')
            ->andReturn([
                ...$this->actionResult(true, null, 'action:delete'),
                'data' => [
                    'action' => 'deleted',
                    'plugin_file' => 'sample/sample.php',
                ],
            ]);

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('deleted', $item->operation_result);
        $this->assertSame('1.1.5', $item->version_before);
        $this->assertNull($item->version_after);
    }

    public function test_delete_skips_when_inventory_has_no_matching_folder(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('delete');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'other-plugin',
                'version' => '2.0.0',
                'plugin_file' => 'other-plugin/plugin.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('fetchPluginInfo')->once()->andReturn([
            'ok' => true,
            'found' => false,
            'version' => null,
            'active' => null,
            'plugin_file' => null,
            'message' => 'Plugin not installed',
            'error_code' => 'plugin_not_found',
            'http_status' => 404,
            'response_time_ms' => 5,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins/sample',
            'probe_method' => 'single_plugin',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'single_plugin', 'http_status' => 404]],
        ]);
        $remote->shouldNotReceive('deletePlugin');

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('skipped', $item->item_status);
        $this->assertSame('plugin_not_found', $item->error_code);
        $this->assertStringContainsString('sample', (string) $item->message);
        $this->assertStringContainsString('other-plugin', (string) $item->message);
    }

    public function test_activate_recovers_via_single_plugin_lookup_when_inventory_misses(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('activate');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'other-plugin',
                'version' => '2.0.0',
                'plugin_file' => 'other-plugin/plugin.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('fetchPluginInfo')->once()->andReturn([
            'ok' => true,
            'found' => true,
            'version' => '1.2.3',
            'active' => false,
            'plugin_file' => 'sample/sample.php',
            'message' => 'OK',
            'error_code' => null,
            'http_status' => 200,
            'response_time_ms' => 5,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins/sample',
            'probe_method' => 'single_plugin',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'single_plugin', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('activate')
            ->once()
            ->withArgs(fn ($domain, $sentPackage, $targetVersion) => $sentPackage === $package
                && $targetVersion === '1.2.3')
            ->andReturn([
                ...$this->actionResult(true, null, 'action:activate'),
                'data' => ['action' => 'activated', 'plugin_file' => 'sample/sample.php'],
            ]);

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('activated', $item->operation_result);
    }

    public function test_delete_attempts_remote_when_inventory_and_lookup_are_inconclusive(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('delete');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'other-plugin',
                'version' => '2.0.0',
                'plugin_file' => 'other-plugin/plugin.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('fetchPluginInfo')->once()->andReturn([
            'ok' => false,
            'found' => null,
            'version' => null,
            'active' => null,
            'plugin_file' => null,
            'message' => 'Gateway timeout',
            'error_code' => 'remote_unavailable',
            'http_status' => 504,
            'response_time_ms' => 5,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins/sample',
            'probe_method' => 'single_plugin',
            'retry_count' => 1,
            'audit' => [['probe_method' => 'single_plugin', 'http_status' => 504]],
        ]);
        $remote->shouldReceive('deletePlugin')
            ->once()
            ->withArgs(fn ($domain, $sentPackage, $pluginFile, $targetVersion) => $sentPackage === $package
                && $pluginFile === null
                && $targetVersion === null)
            ->andReturn([
                ...$this->actionResult(true, null, 'action:delete'),
                'data' => ['action' => 'deleted', 'plugin_file' => 'sample/sample.php'],
            ]);

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('deleted', $item->operation_result);
    }

    public function test_delete_matches_plugin_file_folder_when_inventory_slug_is_text_domain(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('delete');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'sample-text-domain',
                'name' => 'Sample Plugin',
                'version' => '1.2.3',
                'plugin_file' => 'sample/sample.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('deletePlugin')
            ->once()
            ->withArgs(fn ($domain, $sentPackage, $pluginFile, $targetVersion) => $pluginFile === 'sample/sample.php'
                && $targetVersion === '1.2.3')
            ->andReturn([
                ...$this->actionResult(true, null, 'action:delete'),
                'data' => ['action' => 'deleted', 'plugin_file' => 'sample/sample.php'],
            ]);

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('deleted', $item->operation_result);
    }

    public function test_delete_matches_unique_plugin_name_and_version_when_folder_differs(): void
    {
        config()->set('plugin_manager.require_inventory', true);
        [, $item, $deployment, $package] = $this->records('delete');
        $remote = $this->remoteWithHealthyAgent();
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [[
                'slug' => 'old-sample-folder',
                'name' => 'Sample Plugin',
                'version' => '1.2.3',
                'plugin_file' => 'old-sample-folder/plugin.php',
                'active' => true,
            ]],
            'message' => 'OK',
            'http_status' => 200,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins',
            'probe_method' => 'inventory',
            'retry_count' => 0,
            'audit' => [['probe_method' => 'inventory', 'http_status' => 200]],
        ]);
        $remote->shouldReceive('deletePlugin')
            ->once()
            ->withArgs(fn ($domain, $sentPackage, $pluginFile, $targetVersion) => $pluginFile === 'old-sample-folder/plugin.php'
                && $targetVersion === '1.2.3')
            ->andReturn([
                ...$this->actionResult(true, null, 'action:delete'),
                'data' => ['action' => 'deleted', 'plugin_file' => 'old-sample-folder/plugin.php'],
            ]);

        $this->invokeProcessItem($this->service($remote), $deployment, $package, $item);

        $item->refresh();
        $this->assertSame('success', $item->item_status);
        $this->assertSame('deleted', $item->operation_result);
        $this->assertSame('old-sample-folder/plugin.php', $item->plugin_file);
    }

    private function records(string $operation): array
    {
        $domain = Domain::query()->create(['name' => 'site.example', 'api_key' => 'secret', 'admin_id' => 1]);
        $item = PluginDeploymentItem::query()->create([
            'plugin_deployment_id' => 10,
            'domain' => $domain->name,
            'domain_id' => $domain->id,
            'item_status' => 'processing',
        ]);
        $deployment = new PluginDeployment([
            'operation' => $operation,
            'activate_after' => true,
            'skip_if_same_version' => true,
        ]);
        $package = new PluginPackage([
            'slug' => 'sample-version-1.2.3',
            'expected_slug' => 'sample',
            'name' => 'Sample Plugin',
            'version' => '1.2.3',
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        return [$domain, $item, $deployment, $package];
    }

    private function remoteWithHealthyAgent(): RemotePluginManagerService
    {
        $remote = Mockery::mock(RemotePluginManagerService::class);
        $remote->shouldReceive('fetchAgentStatus')->once()->andReturn([
            'ok' => true,
            'code' => 'online',
            'plugin_manager_supported' => true,
            'plugin_version' => '8.1.5',
            'message' => 'Connected',
            'probe_method' => 'rest',
            'http_status' => 200,
            'response_time_ms' => 5,
        ]);
        $remote->shouldReceive('agentStatusAudit')->once()->andReturn([
            'probe_method' => 'rest',
            'http_status' => 200,
        ]);

        return $remote;
    }

    private function service(RemotePluginManagerService $remote): PluginDeploymentService
    {
        return new PluginDeploymentService(
            app(DomainStatusCheckerService::class),
            $remote,
            app(PluginPackageService::class),
            app(PluginDeployPreflightService::class),
        );
    }

    private function invokeProcessItem(
        PluginDeploymentService $service,
        PluginDeployment $deployment,
        PluginPackage $package,
        PluginDeploymentItem $item
    ): void {
        $method = new \ReflectionMethod($service, 'processItem');
        $method->invoke($service, $deployment, $package, $item, 'https://dashboard.example/download');
    }

    private function actionResult(bool $success, ?string $errorCode, string $probeMethod): array
    {
        return [
            'success' => $success,
            'message' => $success ? 'Installed' : 'Missing',
            'error_code' => $errorCode,
            'data' => $success
                ? ['action' => 'installed', 'new_version' => '1.2.3', 'plugin_file' => 'sample/sample.php']
                : [],
            'response_time_ms' => 10,
            'http_status' => $success ? 200 : 404,
            'request_url' => 'https://site.example/wp-json/external/v1/plugins/'.str_replace('action:', '', $probeMethod),
            'probe_method' => $probeMethod,
            'retry_count' => 0,
            'audit' => [['probe_method' => $probeMethod, 'http_status' => $success ? 200 : 404]],
        ];
    }
}
