<?php

namespace Tests\Feature;

use App\Data\AgentStatusResult;
use App\Jobs\ProcessDomainStatusCheckChunkJob;
use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use App\Services\DomainStatusCheckerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainRecheckDisconnectedTest extends TestCase
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
            'domain_status_checker.disconnected_max' => 10000,
            'queue.default' => 'sync',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
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
    }

    public function test_start_only_includes_disconnected_domains_and_respects_category(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $gaming = DomainCategory::query()->create(['name' => 'Gaming', 'slug' => 'gaming']);
        $news = DomainCategory::query()->create(['name' => 'News', 'slug' => 'news']);

        Domain::query()->create([
            'name' => 'down-gaming.example',
            'domain_category_id' => $gaming->id,
            'admin_id' => $admin->id,
            'status' => 0,
        ]);
        Domain::query()->create([
            'name' => 'up-gaming.example',
            'domain_category_id' => $gaming->id,
            'admin_id' => $admin->id,
            'status' => 1,
        ]);
        Domain::query()->create([
            'name' => 'down-news.example',
            'domain_category_id' => $news->id,
            'admin_id' => $admin->id,
            'status' => 0,
        ]);

        $response = $this->postJson(route('admin.domain.recheck-disconnected.start'), [
            'domain_category_id' => $gaming->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1);

        $check = DomainStatusCheck::query()->where('uuid', $response->json('check_uuid'))->first();
        $this->assertNotNull($check);
        $this->assertSame('disconnected', $check->source);
        $this->assertTrue((bool) $check->update_inventory);

        $domains = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->pluck('domain')
            ->all();

        $this->assertSame(['down-gaming.example'], $domains);

        Queue::assertPushed(ProcessDomainStatusCheckChunkJob::class);
    }

    public function test_successful_probe_marks_inventory_connected(): void
    {
        $admin = $this->createAdmin();

        $domain = Domain::query()->create([
            'name' => 'recover.example',
            'admin_id' => $admin->id,
            'status' => 0,
        ]);

        Queue::fake();

        $service = app(DomainStatusCheckerService::class);
        $check = $service->createCheck(
            (int) $admin->id,
            ['recover.example'],
            'disconnected',
            true,
            null,
            true
        );

        $item = DomainStatusCheckItem::query()
            ->where('domain_status_check_id', $check->id)
            ->firstOrFail();

        $service->applyProbeResults($item, [
            'connected' => true,
            'status_code' => 'online',
            'message' => 'Agent online',
            'probe_method' => 'auth_rest',
            'agent_version' => '7.8.0',
            'http_status' => 200,
            'response_time_ms' => 110,
            'agent_result' => new AgentStatusResult(
                ok: true,
                code: 'online',
                message: 'Agent online',
                pluginVersion: '7.8.0',
                probeMethod: 'auth_rest',
                httpStatus: 200,
                responseTimeMs: 110,
            ),
        ], false);

        $service->recalculateCheckStats($check->fresh());
        $service->finalizeCheck($check->fresh());

        $domain->refresh();
        $this->assertSame(1, (int) $domain->status);
        $this->assertSame('online', $domain->last_status_code);
    }

    public function test_csv_export_for_completed_recheck(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'completed',
            'phase' => 'done',
            'total_count' => 1,
            'processed_count' => 1,
            'connected_count' => 1,
            'disconnected_count' => 0,
            'in_inventory_count' => 1,
            'inventory_updated_count' => 1,
            'update_inventory' => true,
            'use_authenticated_check' => true,
            'status_message' => 'Check completed',
            'completed_at' => now(),
        ]);

        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'recovered.example',
            'sort_order' => 1,
            'check_status' => 'connected',
            'connected' => true,
            'status_code' => 'online',
            'probe_method' => 'auth_rest',
            'message' => 'Agent online',
            'attempts' => 1,
            'response_time_ms' => 99,
            'in_inventory' => true,
            'category' => 'Gaming',
            'checked_at' => now(),
        ]);

        $response = $this->get(route('admin.domain.recheck-disconnected.export', $check->uuid));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Previous Status', $csv);
        $this->assertStringContainsString('New Status', $csv);
        $this->assertStringContainsString('recovered.example', $csv);
        $this->assertStringContainsString('Connected', $csv);
        $this->assertStringContainsString('Disconnected', $csv);
    }

    public function test_index_shows_disconnected_scope(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        Domain::query()->create([
            'name' => 'offline.example',
            'admin_id' => $admin->id,
            'status' => 0,
        ]);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertSee('Recheck Disconnected')
            ->assertSee('Recheck disconnected')
            ->assertSee('Inventory still marked disconnected')
            ->assertSee('Total')
            ->assertSee('Connected')
            ->assertSee('Disconnected')
            ->assertSee('Errors')
            ->assertSee('1'); // inventory disconnected count
    }

    public function test_index_resumes_in_progress_check_for_current_admin(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'running',
            'phase' => 'initial',
            'total_count' => 2,
            'processed_count' => 1,
            'connected_count' => 1,
            'disconnected_count' => 0,
            'in_inventory_count' => 2,
            'inventory_updated_count' => 0,
            'update_inventory' => true,
            'use_authenticated_check' => true,
            'status_message' => 'Checking disconnected domains...',
            'started_at' => now(),
        ]);

        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'up-again.example',
            'sort_order' => 1,
            'check_status' => 'connected',
            'connected' => true,
            'status_code' => 'online',
            'attempts' => 1,
            'in_inventory' => true,
            'checked_at' => now(),
        ]);

        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'still-down.example',
            'sort_order' => 2,
            'check_status' => 'retry_pending',
            'connected' => false,
            'attempts' => 2,
            'in_inventory' => true,
            'next_retry_at' => now()->addMinutes(2),
        ]);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertSee('data-resume-uuid="'.$check->uuid.'"', false)
            ->assertSee('id="rdResumeBootstrap"', false)
            ->assertSee($check->uuid)
            ->assertDontSee('up-again.example')
            ->assertDontSee('still-down.example')
            ->assertSee('Rechecking...')
            ->assertSee('Total')
            ->assertSee('Connected')
            ->assertSee('Disconnected')
            ->assertSee('id="rdSummaryTotal">2</', false)
            ->assertSee('id="rdSummaryConnected">1</', false)
            ->assertSee('id="rdSummaryStill">0</', false);

        $progress = $this->getJson(route('admin.domain.recheck-disconnected.progress', $check->uuid));
        $progress->assertOk();
        $domains = collect($progress->json('results'))->pluck('domain');
        $this->assertTrue($domains->contains('up-again.example'));
        $this->assertTrue($domains->contains('still-down.example'));
    }

    public function test_index_does_not_resume_another_admins_check(): void
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

        $otherCheck = DomainStatusCheck::query()->create([
            'admin_id' => $other->id,
            'source' => 'disconnected',
            'status' => 'running',
            'phase' => 'initial',
            'total_count' => 1,
            'processed_count' => 0,
            'connected_count' => 0,
            'disconnected_count' => 0,
            'in_inventory_count' => 1,
            'inventory_updated_count' => 0,
            'update_inventory' => true,
            'use_authenticated_check' => true,
            'status_message' => 'Other admin check',
            'started_at' => now(),
        ]);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertDontSee('data-resume-uuid="'.$otherCheck->uuid.'"', false)
            ->assertDontSee('id="rdResumeBootstrap"', false)
            ->assertDontSee($otherCheck->uuid);
    }

    public function test_index_resumes_recently_completed_check(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'completed',
            'phase' => 'done',
            'total_count' => 1,
            'processed_count' => 1,
            'connected_count' => 1,
            'disconnected_count' => 0,
            'in_inventory_count' => 1,
            'inventory_updated_count' => 1,
            'update_inventory' => true,
            'use_authenticated_check' => true,
            'status_message' => 'Check completed',
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(10),
        ]);

        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'recovered.example',
            'sort_order' => 1,
            'check_status' => 'connected',
            'connected' => true,
            'status_code' => 'online',
            'attempts' => 1,
            'in_inventory' => true,
            'checked_at' => now()->subMinutes(10),
        ]);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertSee('data-resume-uuid="'.$check->uuid.'"', false)
            ->assertSee('Recheck complete')
            ->assertDontSee('recovered.example')
            ->assertSee('id="rdSummaryTotal">1</', false)
            ->assertSee('id="rdSummaryConnected">1</', false)
            ->assertSee('id="rdSummaryStill">0</', false);

        $this->getJson(route('admin.domain.recheck-disconnected.progress', $check->uuid))
            ->assertOk()
            ->assertJsonPath('results.0.domain', 'recovered.example')
            ->assertJsonCount(1, 'results');
    }

    public function test_progress_returns_the_latest_rows_and_a_cursor_past_them(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'processing',
            'phase' => 'initial',
            'total_count' => 101,
            'processed_count' => 0,
            'status_message' => 'Checking',
            'started_at' => now(),
        ]);

        $base = now()->subHours(3);
        $oldestId = null;
        $newestUpdatedAt = null;

        foreach (range(1, 101) as $number) {
            $item = DomainStatusCheckItem::query()->create([
                'domain_status_check_id' => $check->id,
                'domain' => sprintf('row-%03d.example', $number),
                'sort_order' => $number,
                'check_status' => 'connected',
            ]);
            $updatedAt = $base->copy()->addMinutes($number)->format('Y-m-d H:i:s');
            DomainStatusCheckItem::query()->whereKey($item->id)->update([
                'updated_at' => $updatedAt,
            ]);
            if ($number === 1) {
                $oldestId = $item->id;
            }
            if ($number === 101) {
                $newestUpdatedAt = $updatedAt;
            }
        }

        $first = $this->getJson(route('admin.domain.recheck-disconnected.progress', $check->uuid));
        $first->assertOk()
            ->assertJsonCount(DomainStatusCheckerService::DISCONNECTED_RESULT_WINDOW, 'results')
            ->assertJsonPath('cursor', $newestUpdatedAt.'|'.$first->json('results.0.id'));

        $domains = collect($first->json('results'))->pluck('domain');
        $this->assertTrue($domains->contains('row-101.example'));
        $this->assertFalse($domains->contains('row-001.example'));

        $isoSince = $this->getJson(route('admin.domain.recheck-disconnected.progress', [
            'uuid' => $check->uuid,
            'since' => now()->toIso8601String(),
        ]));
        $isoSince->assertOk()->assertJsonCount(DomainStatusCheckerService::DISCONNECTED_RESULT_WINDOW, 'results');

        $cursor = $first->json('cursor');
        $this->getJson(route('admin.domain.recheck-disconnected.progress', [
            'uuid' => $check->uuid,
            'since' => $cursor,
        ]))->assertOk()
            ->assertJsonCount(0, 'results')
            ->assertJsonPath('cursor', null);

        DomainStatusCheckItem::query()->whereKey($oldestId)->update([
            'updated_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'check_status' => 'connected',
        ]);

        $next = $this->getJson(route('admin.domain.recheck-disconnected.progress', [
            'uuid' => $check->uuid,
            'since' => $cursor,
        ]));
        $next->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.domain', 'row-001.example');
    }

    public function test_progress_without_a_cursor_skips_untouched_pending_domains(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'processing',
            'phase' => 'initial',
            'total_count' => 120,
            'processed_count' => 5,
            'connected_count' => 5,
            'status_message' => 'Checking',
            'started_at' => now(),
        ]);

        $insertedAt = now()->subHour()->format('Y-m-d H:i:s');
        $checkedAt = now()->subMinute()->format('Y-m-d H:i:s');

        foreach (range(1, 120) as $number) {
            $item = DomainStatusCheckItem::query()->create([
                'domain_status_check_id' => $check->id,
                'domain' => sprintf('row-%03d.example', $number),
                'sort_order' => $number,
                'check_status' => $number <= 5 ? 'connected' : 'pending',
                'connected' => $number <= 5,
            ]);
            DomainStatusCheckItem::query()->whereKey($item->id)->update([
                'updated_at' => $number <= 5 ? $checkedAt : $insertedAt,
            ]);
        }

        $response = $this->getJson(route('admin.domain.recheck-disconnected.progress', $check->uuid));
        $response->assertOk()->assertJsonCount(5, 'results');

        $domains = collect($response->json('results'))->pluck('domain');
        $this->assertTrue($domains->contains('row-001.example'));
        $this->assertFalse($domains->contains('row-120.example'));
    }

    public function test_finished_progress_ignores_an_old_cursor_and_returns_the_newest_rows(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = DomainStatusCheck::query()->create([
            'admin_id' => $admin->id,
            'source' => 'disconnected',
            'status' => 'completed',
            'phase' => 'done',
            'total_count' => 150,
            'processed_count' => 150,
            'connected_count' => 150,
            'status_message' => 'Check completed',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subMinute(),
        ]);

        $base = now()->subHours(3);
        $oldestCursor = null;

        foreach (range(1, 150) as $number) {
            $item = DomainStatusCheckItem::query()->create([
                'domain_status_check_id' => $check->id,
                'domain' => sprintf('row-%03d.example', $number),
                'sort_order' => $number,
                'check_status' => 'connected',
                'connected' => true,
            ]);
            $updatedAt = $base->copy()->addMinutes($number)->format('Y-m-d H:i:s');
            DomainStatusCheckItem::query()->whereKey($item->id)->update([
                'updated_at' => $updatedAt,
            ]);
            if ($number === 1) {
                $oldestCursor = $updatedAt.'|'.$item->id;
            }
        }

        $response = $this->getJson(route('admin.domain.recheck-disconnected.progress', [
            'uuid' => $check->uuid,
            'since' => $oldestCursor,
        ]));

        $response->assertOk()
            ->assertJsonCount(DomainStatusCheckerService::DISCONNECTED_RESULT_WINDOW, 'results');

        $domains = collect($response->json('results'))->pluck('domain');
        $this->assertTrue($domains->contains('row-150.example'));
        $this->assertFalse($domains->contains('row-002.example'));
    }

    public function test_purge_deletes_only_this_checks_domain_check_chunk_jobs(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->nullable();
        });

        $escapedTwelve = json_encode([
            'displayName' => ProcessDomainStatusCheckChunkJob::class,
            'command' => 'O:48:"App\\Jobs\\ProcessDomainStatusCheckChunkJob":1:{s:7:"checkId";i:12;}',
        ]);
        $escapedLongerId = json_encode([
            'displayName' => ProcessDomainStatusCheckChunkJob::class,
            'command' => 'O:48:"App\\Jobs\\ProcessDomainStatusCheckChunkJob":1:{s:7:"checkId";i:123;}',
        ]);
        $plainTwelve = 'ProcessDomainStatusCheckChunkJob s:7:"checkId";i:12;';

        DB::table('jobs')->insert([
            [
                'queue' => 'domainCheck',
                'payload' => $escapedTwelve,
                'attempts' => 0,
                'available_at' => 1,
                'created_at' => 1,
            ],
            [
                'queue' => 'domainCheck',
                'payload' => $escapedLongerId,
                'attempts' => 0,
                'available_at' => 1,
                'created_at' => 1,
            ],
            [
                'queue' => 'campaigns',
                'payload' => $escapedTwelve,
                'attempts' => 0,
                'available_at' => 1,
                'created_at' => 1,
            ],
            [
                'queue' => 'domainCheck',
                'payload' => 'App\\Jobs\\PublishCampaignPostJob',
                'attempts' => 0,
                'available_at' => 1,
                'created_at' => 1,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'domainCheck',
            'payload' => $plainTwelve,
            'exception' => 'failed',
            'failed_at' => now(),
        ]);

        $deleted = app(DomainStatusCheckerService::class)->purgeQueuedJobsForCheck(12);

        $this->assertSame(2, $deleted);
        $this->assertSame(0, DB::table('jobs')->where('queue', 'domainCheck')->where('payload', $escapedTwelve)->count());
        $this->assertSame(1, DB::table('jobs')->where('payload', $escapedLongerId)->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'campaigns')->count());
        $this->assertSame(1, DB::table('jobs')->where('payload', 'App\\Jobs\\PublishCampaignPostJob')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
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
