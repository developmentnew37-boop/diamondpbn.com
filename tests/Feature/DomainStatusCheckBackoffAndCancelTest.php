<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use App\Services\DomainStatusCheckerService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainStatusCheckBackoffAndCancelTest extends TestCase
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
            'domain_status_checker.max_attempts' => 5,
            'domain_status_checker.backoff_seconds' => [60, 120, 240, 300],
            'queue.default' => 'sync',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
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
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->integer('status')->default(0);
            $table->text('api_key')->nullable();
            $table->string('api_key_lookup_hash')->nullable();
            $table->string('agent_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_status_code')->nullable();
            $table->string('last_status_probe')->nullable();
            $table->text('last_status_message')->nullable();
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

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function test_failed_attempt_schedules_backoff_then_disconnects_after_fifth(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');

        $service = app(DomainStatusCheckerService::class);
        $item = new DomainStatusCheckItem([
            'domain' => 'flaky.example',
            'sort_order' => 1,
            'check_status' => 'pending',
            'attempts' => 0,
        ]);
        $item->id = 1;

        // Simulate without persisting — use real model via DB
        $admin = $this->createAdmin();
        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'processing',
            'phase' => 'initial',
            'total_count' => 1,
            'update_inventory' => false,
        ]);
        $item = DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'flaky.example',
            'sort_order' => 1,
            'check_status' => 'pending',
            'attempts' => 0,
        ]);

        $fail = [
            'connected' => false,
            'status_code' => 'firewall_blocked',
            'message' => 'HTTP 403: Firewall blocked the authenticated status check. Whitelist the dashboard server IP for POST /wp-json/external/v1/status/check. '.str_repeat('x', 300),
            'probe_method' => 'auth_rest',
            'http_status' => 403,
            'response_time_ms' => 1500,
        ];

        $expectedDelays = [60, 120, 240, 300];
        foreach ($expectedDelays as $index => $delay) {
            $service->applyProbeResults($item->fresh(), $fail);
            $item->refresh();
            $this->assertSame('retry_pending', $item->check_status);
            $this->assertSame($index + 1, (int) $item->attempts);
            $this->assertNotNull($item->next_retry_at);
            $this->assertTrue(
                $item->next_retry_at->equalTo(now()->addSeconds($delay)),
                "Attempt ".($index + 1)." should wait {$delay}s"
            );
            $this->assertLessThanOrEqual(2000, mb_strlen((string) $item->message));
        }

        $service->applyProbeResults($item->fresh(), $fail);
        $item->refresh();
        $this->assertSame('disconnected', $item->check_status);
        $this->assertSame(5, (int) $item->attempts);
        $this->assertNull($item->next_retry_at);
        $this->assertStringContainsString('still failing after 5 attempts', (string) $item->message);

        Carbon::setTestNow();
    }

    public function test_cancel_check_stops_and_does_not_leave_pending_work(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $service = app(DomainStatusCheckerService::class);
        $check = $service->createCheck(
            (int) $admin->id,
            ['stuck.example'],
            'disconnected',
            true,
            null,
            true
        );

        Domain::query()->create([
            'name' => 'stuck.example',
            'admin_id' => $admin->id,
            'status' => 0,
        ]);

        $response = $this->postJson(route('admin.domain.recheck-disconnected.cancel', $check->uuid));
        $response->assertOk()->assertJsonPath('success', true);

        $check->refresh();
        $this->assertSame('cancelled', $check->status);
        $this->assertTrue($check->isFinished());

        $pending = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->whereIn('check_status', ['pending', 'checking', 'retry_pending'])
            ->count();
        $this->assertSame(0, $pending);
    }

    public function test_cancel_stuck_command_cancels_processing_runs(): void
    {
        $admin = $this->createAdmin();
        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'processing',
            'phase' => 'backoff',
            'total_count' => 1,
            'update_inventory' => true,
            'status_message' => 'stuck',
        ]);
        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'left.example',
            'sort_order' => 1,
            'check_status' => 'retry_pending',
            'attempts' => 2,
            'next_retry_at' => now()->addMinutes(5),
        ]);

        $this->artisan('domain-status-checks:cancel-stuck')
            ->assertSuccessful();

        $check->refresh();
        $this->assertSame('cancelled', $check->status);
    }

    public function test_backoff_helpers_match_config(): void
    {
        $service = app(DomainStatusCheckerService::class);
        $this->assertSame(5, $service->maxAttempts());
        $this->assertSame(60, $service->backoffSecondsAfterAttempt(1));
        $this->assertSame(120, $service->backoffSecondsAfterAttempt(2));
        $this->assertSame(240, $service->backoffSecondsAfterAttempt(3));
        $this->assertSame(300, $service->backoffSecondsAfterAttempt(4));
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
