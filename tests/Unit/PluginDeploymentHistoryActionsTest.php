<?php

namespace Tests\Unit;

use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginDeploymentItem;
use App\Models\Admin\PluginPackage;
use App\Services\DomainStatusCheckerService;
use App\Services\PluginDeployPreflightService;
use App\Services\PluginDeploymentService;
use App\Services\PluginPackageService;
use App\Services\RemotePluginManagerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PluginDeploymentHistoryActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('plugin_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->string('slug');
            $table->string('expected_slug')->nullable();
            $table->string('name');
            $table->string('version');
            $table->string('original_filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_diamond_pbn_agent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('plugin_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->unsignedBigInteger('plugin_package_id');
            $table->string('operation', 20);
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->string('status', 20)->default('completed');
            $table->string('phase', 20)->default('initial');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->boolean('activate_after')->default(true);
            $table->boolean('skip_if_same_version')->default(true);
            $table->text('status_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plugin_deployment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plugin_deployment_id');
            $table->string('domain');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('item_status', 20)->default('pending');
            $table->string('operation_result', 20)->nullable();
            $table->string('version_before')->nullable();
            $table->string('version_after')->nullable();
            $table->string('error_code')->nullable();
            $table->string('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('in_inventory')->default(false);
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('plugin_deployment_items');
        Schema::dropIfExists('plugin_deployments');
        Schema::dropIfExists('plugin_packages');

        parent::tearDown();
    }

    public function test_history_filters_by_status_operation_outcome_and_search(): void
    {
        $package = PluginPackage::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'slug' => 'sample-version-1.0.0',
            'expected_slug' => 'sample',
            'name' => 'Sample Plugin',
            'version' => '1.0.0',
            'original_filename' => 'sample.zip',
            'storage_path' => 'plugin-packages/x/sample.zip',
            'file_size_bytes' => 10,
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $match = PluginDeployment::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'plugin_package_id' => $package->id,
            'operation' => 'delete',
            'status' => 'completed',
            'failed_count' => 2,
            'skipped_count' => 1,
            'success_count' => 0,
            'total_count' => 3,
            'processed_count' => 3,
        ]);

        PluginDeployment::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'plugin_package_id' => $package->id,
            'operation' => 'install',
            'status' => 'completed',
            'failed_count' => 0,
            'skipped_count' => 0,
            'success_count' => 5,
            'total_count' => 5,
            'processed_count' => 5,
        ]);

        $service = $this->service();

        $filtered = $service->applyHistoryFilters(PluginDeployment::query(), [
            'status' => 'completed',
            'operation' => 'delete',
            'outcome' => 'has_skipped',
            'q' => 'Sample',
        ])->pluck('id')->all();

        $this->assertSame([$match->id], $filtered);
    }

    public function test_retry_failed_or_skipped_includes_skipped_domains_in_new_deploy(): void
    {
        $package = PluginPackage::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'slug' => 'sample-version-1.0.0',
            'expected_slug' => 'sample',
            'name' => 'Sample Plugin',
            'version' => '1.0.0',
            'original_filename' => 'sample.zip',
            'storage_path' => 'plugin-packages/x/sample.zip',
            'file_size_bytes' => 10,
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $original = PluginDeployment::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'plugin_package_id' => $package->id,
            'operation' => 'activate',
            'status' => 'completed',
            'activate_after' => true,
            'skip_if_same_version' => true,
            'failed_count' => 1,
            'skipped_count' => 1,
            'success_count' => 1,
            'total_count' => 3,
            'processed_count' => 3,
        ]);

        PluginDeploymentItem::query()->create([
            'plugin_deployment_id' => $original->id,
            'domain' => 'ok.example',
            'sort_order' => 1,
            'item_status' => 'success',
        ]);
        PluginDeploymentItem::query()->create([
            'plugin_deployment_id' => $original->id,
            'domain' => 'fail.example',
            'sort_order' => 2,
            'item_status' => 'failed',
        ]);
        PluginDeploymentItem::query()->create([
            'plugin_deployment_id' => $original->id,
            'domain' => 'skip.example',
            'sort_order' => 3,
            'item_status' => 'skipped',
        ]);

        $service = Mockery::mock(PluginDeploymentService::class, [
            app(DomainStatusCheckerService::class),
            Mockery::mock(RemotePluginManagerService::class),
            app(PluginPackageService::class),
            app(PluginDeployPreflightService::class),
        ])->makePartial();

        $service->shouldReceive('createDeployment')
            ->once()
            ->withArgs(function ($adminId, $pkg, $operation, $domains) use ($package) {
                sort($domains);

                return $adminId === 7
                    && $pkg->is($package)
                    && $operation === 'activate'
                    && $domains === ['fail.example', 'skip.example'];
            })
            ->andReturn(new PluginDeployment([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'operation' => 'activate',
            ]));

        $retry = $service->retryFailedOrSkipped($original, 7, 'both');

        $this->assertSame('activate', $retry->operation);
    }

    public function test_retry_scope_failed_only_excludes_skipped(): void
    {
        $package = PluginPackage::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'slug' => 'sample-version-1.0.0',
            'expected_slug' => 'sample',
            'name' => 'Sample Plugin',
            'version' => '1.0.0',
            'original_filename' => 'sample.zip',
            'storage_path' => 'plugin-packages/x/sample.zip',
            'file_size_bytes' => 10,
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $original = PluginDeployment::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => 1,
            'plugin_package_id' => $package->id,
            'operation' => 'delete',
            'status' => 'completed',
            'activate_after' => false,
            'skip_if_same_version' => true,
            'failed_count' => 1,
            'skipped_count' => 1,
            'success_count' => 0,
            'total_count' => 2,
            'processed_count' => 2,
        ]);

        PluginDeploymentItem::query()->create([
            'plugin_deployment_id' => $original->id,
            'domain' => 'fail.example',
            'sort_order' => 1,
            'item_status' => 'failed',
        ]);
        PluginDeploymentItem::query()->create([
            'plugin_deployment_id' => $original->id,
            'domain' => 'skip.example',
            'sort_order' => 2,
            'item_status' => 'skipped',
        ]);

        $service = Mockery::mock(PluginDeploymentService::class, [
            app(DomainStatusCheckerService::class),
            Mockery::mock(RemotePluginManagerService::class),
            app(PluginPackageService::class),
            app(PluginDeployPreflightService::class),
        ])->makePartial();

        $service->shouldReceive('createDeployment')
            ->once()
            ->withArgs(fn ($adminId, $pkg, $operation, $domains) => $domains === ['fail.example'])
            ->andReturn(new PluginDeployment([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'operation' => 'delete',
            ]));

        $service->retryFailedOrSkipped($original, 1, 'failed');
    }

    private function service(): PluginDeploymentService
    {
        return new PluginDeploymentService(
            app(DomainStatusCheckerService::class),
            Mockery::mock(RemotePluginManagerService::class),
            app(PluginPackageService::class),
            app(PluginDeployPreflightService::class),
        );
    }
}
