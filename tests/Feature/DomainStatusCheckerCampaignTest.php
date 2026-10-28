<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use App\Services\DomainStatusCheckCleanupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

class DomainStatusCheckerCampaignTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';

        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_campaigns_list_hides_finished_runs_older_than_retention(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $expired = $this->makeCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHours(13),
        ]);
        $missingCompletedAt = $this->makeCheck($admin->id, [
            'source' => 'inventory',
            'status' => 'completed',
            'completed_at' => null,
        ]);
        DomainStatusCheck::query()->whereKey($missingCompletedAt->id)->update([
            'updated_at' => now()->subHours(13),
        ]);
        $finished = $this->makeCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);
        $running = $this->makeCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
        ]);

        $this->get(route('admin.domain.status-checker.campaigns'))
            ->assertOk()
            ->assertSee('Status campaigns')
            ->assertSee('processing')
            ->assertSee($finished->uuid)
            ->assertSee($running->uuid)
            ->assertDontSee($expired->uuid)
            ->assertDontSee($missingCompletedAt->uuid);

        $this->get(route('admin.domain.status-checker'))
            ->assertOk()
            ->assertSee('Status campaigns')
            ->assertSee('id="statusCheckResumeBootstrap"', false)
            ->assertSee($running->uuid)
            ->assertDontSee($finished->uuid);
    }

    public function test_preview_paginates_and_rejects_other_checks(): void
    {
        $admin = $this->createAdmin();
        $other = Admin::query()->create([
            'name' => 'Other Admin',
            'slug' => 'other-'.uniqid(),
            'email' => 'other-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => Admin::ADMIN,
        ]);
        $this->actingAs($admin, 'admin');

        $check = $this->makeCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now(),
            'total_count' => 51,
        ]);

        foreach (range(1, 51) as $number) {
            DomainStatusCheckItem::query()->create([
                'domain_status_check_id' => $check->id,
                'domain' => sprintf('status-row-%02d.example', $number),
                'sort_order' => $number,
                'check_status' => $number === 51 ? 'disconnected' : 'connected',
                'http_status' => $number === 51 ? 508 : 200,
                'message' => $number === 51 ? 'HTTP 508: Resource Limit Is Reached' : 'Connected',
                'category' => 'Gaming',
            ]);
        }

        $this->get(route('admin.domain.status-checker.campaigns.show', $check->uuid))
            ->assertOk()
            ->assertSee('status-row-01.example')
            ->assertSee('Connected')
            ->assertDontSee('status-row-51.example');

        $this->get(route('admin.domain.status-checker.campaigns.show', [
            'uuid' => $check->uuid,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('status-row-51.example')
            ->assertSee('HTTP 508: Resource Limit Is Reached')
            ->assertDontSee('status-row-01.example');

        $this->get(route('admin.domain.status-checker.campaigns.show', [
            'uuid' => $check->uuid,
            'result' => 'disconnected',
        ]))
            ->assertOk()
            ->assertSee('status-row-51.example')
            ->assertSee('Disconnected 1', false)
            ->assertDontSee('status-row-01.example');

        $otherCheck = $this->makeCheck($other->id, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $disconnected = $this->makeCheck($admin->id, [
            'source' => 'disconnected',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->get(route('admin.domain.status-checker.campaigns.show', $otherCheck->uuid))->assertNotFound();
        $this->get(route('admin.domain.status-checker.campaigns.show', $disconnected->uuid))->assertNotFound();
        $this->get(route('admin.domain.status-checker.export', $otherCheck->uuid))->assertNotFound();
        $this->get(route('admin.domain.status-checker.export', $disconnected->uuid))->assertNotFound();
    }

    public function test_excel_export_includes_the_site_error_and_refuses_a_running_check(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = $this->makeCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now(),
            'connected_count' => 1,
            'disconnected_count' => 1,
        ]);
        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'up.example',
            'sort_order' => 1,
            'check_status' => 'connected',
            'http_status' => 200,
            'message' => 'Connected',
            'category' => 'News',
        ]);
        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'limited.example',
            'sort_order' => 2,
            'check_status' => 'disconnected',
            'status_code' => 'domain_offline',
            'http_status' => 508,
            'message' => 'HTTP 508: Resource Limit Is Reached',
            'category' => 'News',
        ]);

        $response = $this->get(route('admin.domain.status-checker.export', $check->uuid));
        $response->assertOk();
        $rows = $this->excelRows($response->streamedContent());
        $limited = collect($rows)->firstWhere('Domain', 'limited.example');

        $this->assertNotNull($limited);
        $this->assertSame('Disconnected', $limited['Status']);
        $this->assertSame('508', (string) $limited['HTTP']);
        $this->assertSame('HTTP 508: Resource Limit Is Reached', $limited['Message']);

        $running = $this->makeCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
        ]);
        $this->get(route('admin.domain.status-checker.export', $running->uuid))->assertStatus(409);
    }

    public function test_second_start_is_refused_while_a_check_is_unfinished(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');
        $this->makeCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
        ]);

        $this->postJson(route('admin.domain.status-checker.start'), [
            'source' => 'manual',
            'domains_list' => "example.com\n",
        ])->assertStatus(409);
    }

    public function test_cleanup_deletes_finished_status_checks_after_retention(): void
    {
        $admin = $this->createAdmin();
        $expired = $this->makeCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHours(13),
        ]);
        $recent = $this->makeCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);
        $running = $this->makeCheck($admin->id, [
            'status' => 'queued',
            'completed_at' => null,
        ]);

        app(DomainStatusCheckCleanupService::class)->runBatchedCleanup($admin->id);

        $this->assertNull(DomainStatusCheck::query()->find($expired->id));
        $this->assertNotNull(DomainStatusCheck::query()->find($recent->id));
        $this->assertNotNull(DomainStatusCheck::query()->find($running->id));
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('email');
            $table->string('password');
            $table->tinyInteger('type');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_feature_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('permission');
            $table->timestamps();
            $table->unique(['admin_id', 'permission']);
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });

        Schema::create('local_client_rate_lists', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('local_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rate_list_id')->nullable();
        });

        Schema::create('domain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('domain_status_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('admin_id');
            $table->string('source', 20)->default('manual');
            $table->string('status', 20)->default('queued');
            $table->string('phase', 20)->default('initial');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('connected_count')->default(0);
            $table->unsignedInteger('disconnected_count')->default(0);
            $table->unsignedInteger('in_inventory_count')->default(0);
            $table->unsignedInteger('inventory_updated_count')->default(0);
            $table->boolean('update_inventory')->default(false);
            $table->boolean('use_authenticated_check')->default(false);
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_status_check_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_status_check_id');
            $table->string('domain');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('check_status', 20)->default('pending');
            $table->boolean('connected')->nullable();
            $table->string('status_code')->nullable();
            $table->string('probe_method')->nullable();
            $table->string('agent_version')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('in_inventory')->default(false);
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCheck(int $adminId, array $overrides = []): DomainStatusCheck
    {
        return DomainStatusCheck::query()->create(array_merge([
            'admin_id' => $adminId,
            'source' => 'manual',
            'status' => 'completed',
            'phase' => 'done',
            'total_count' => 1,
            'processed_count' => 1,
            'connected_count' => 1,
            'disconnected_count' => 0,
            'in_inventory_count' => 0,
            'inventory_updated_count' => 0,
            'update_inventory' => false,
            'use_authenticated_check' => false,
            'status_message' => 'Check completed',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ], $overrides));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function excelRows(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'status-xlsx');
        $xlsxPath = $path.'.xlsx';
        rename($path, $xlsxPath);
        file_put_contents($xlsxPath, $binary);

        try {
            return SimpleExcelReader::create($xlsxPath, 'xlsx')->getRows()->all();
        } finally {
            @unlink($xlsxPath);
        }
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super-'.uniqid(),
            'email' => 'super-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => Admin::SUPER_ADMIN,
        ]);
    }
}
