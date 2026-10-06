<?php

namespace Tests\Unit;

use App\Models\Admin\Domain;
use App\Models\Admin\PluginInventoryReport;
use App\Services\PluginInventoryReportService;
use App\Services\RemotePluginManagerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

class PluginInventoryReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('api_key')->nullable();
            $table->string('api_key_lookup_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('plugin_inventory_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('admin_id');
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('file_path')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('plugin_inventory_reports');
        Schema::dropIfExists('domains');

        parent::tearDown();
    }

    public function test_report_excel_lists_every_plugin_without_storing_plugin_rows(): void
    {
        Storage::fake('local');

        Domain::withoutEvents(function () {
            Domain::query()->create(['name' => 'one.test', 'api_key' => 'secret-one']);
            Domain::query()->create(['name' => 'two.test', 'api_key' => 'secret-two']);
            Domain::query()->create(['name' => 'broken.test', 'api_key' => 'secret-broken']);
        });

        $remote = Mockery::mock(RemotePluginManagerService::class);
        $remote->shouldReceive('fetchAgentStatus')->andReturnUsing(function (Domain $domain) {
            if ($domain->name === 'broken.test') {
                throw new \RuntimeException('socket down');
            }

            if ($domain->name === 'two.test') {
                return [
                    'ok' => true,
                    'plugin_manager_supported' => false,
                    'plugin_version' => '7.0.0',
                    'message' => 'Connected',
                ];
            }

            return [
                'ok' => true,
                'plugin_manager_supported' => true,
                'plugin_version' => '8.4.3',
                'message' => 'Connected',
            ];
        });
        $remote->shouldReceive('fetchPluginsInventory')->once()->andReturn([
            'ok' => true,
            'plugins' => [
                ['slug' => 'akismet', 'name' => '=Akismet', 'version' => '5.3', 'active' => true],
                ['slug' => 'external-api-manager', 'name' => 'Diamond PBN', 'version' => '8.4.3', 'active' => true],
                ['slug' => 'custom-blog', 'name' => '', 'version' => '2.6', 'active' => true],
            ],
        ]);
        $this->app->instance(RemotePluginManagerService::class, $remote);

        $report = app(PluginInventoryReportService::class)->createReport(1, [
            'one.test',
            'two.test',
            'missing.test',
            'broken.test',
        ], 'manual', null);

        $report->refresh();

        $this->assertSame('completed', $report->status);
        $this->assertSame(4, (int) $report->processed_count);
        $this->assertSame(1, (int) $report->success_count);
        $this->assertSame(3, (int) $report->failed_count);
        $this->assertSame(1, PluginInventoryReport::query()->count());
        $this->assertNotContains('plugin_name', Schema::getColumnListing('plugin_inventory_reports'));
        $this->assertStringNotContainsString('Akismet', json_encode($report->getAttributes()));

        $path = app(PluginInventoryReportService::class)->workbookAbsolutePath($report);
        $this->assertNotNull($path);

        $rows = SimpleExcelReader::create($path)->getRows()->values()->all();

        $this->assertCount(4, $rows);
        $this->assertSame('one.test', $rows[0]['Domain']);
        $this->assertSame(3, (int) $rows[0]['Plugin count']);
        $this->assertSame("'=Akismet - (5.3) - active", $rows[0]['Plugin name - (1)']);
        $this->assertSame('Diamond PBN - (8.4.3) - active', $rows[0]['Plugin name - (2)']);
        $this->assertSame('custom-blog - (2.6) - active', $rows[0]['Plugin name - (3)']);
        $this->assertSame('', $rows[0]['Message']);
        $this->assertSame('two.test', $rows[1]['Domain']);
        $this->assertSame('', $rows[1]['Plugin name - (1)']);
        $this->assertStringContainsString('does not support plugin listing', $rows[1]['Message']);
        $this->assertSame('missing.test', $rows[2]['Domain']);
        $this->assertStringContainsString('missing API key', $rows[2]['Message']);
        $this->assertSame('broken.test', $rows[3]['Domain']);
        $this->assertSame('Could not read installed plugins', $rows[3]['Message']);

        Storage::disk('local')->assertMissing('plugin-reports/'.$report->uuid.'/domains.json');
        Storage::disk('local')->assertMissing('plugin-reports/'.$report->uuid.'/sites/0.json');
    }

    public function test_missing_domain_list_fails_instead_of_repeating(): void
    {
        Storage::fake('local');
        Queue::fake();

        $report = app(PluginInventoryReportService::class)->createReport(1, ['one.test'], 'manual', null);
        Storage::disk('local')->delete('plugin-reports/'.$report->uuid.'/domains.json');

        app(PluginInventoryReportService::class)->processNextChunk($report);
        $report->refresh();

        $this->assertSame('failed', $report->status);
        $this->assertSame(0, (int) $report->processed_count);
    }
}
