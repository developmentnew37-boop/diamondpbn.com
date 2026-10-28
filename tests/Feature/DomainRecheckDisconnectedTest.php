<?php

namespace Tests\Feature;

use App\Data\AgentStatusResult;
use App\Jobs\ProcessDomainStatusCheckChunkJob;
use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainStatusCheck;
use App\Models\Admin\DomainStatusCheckItem;
use App\Services\DomainStatusCheckCleanupService;
use App\Services\DomainStatusCheckerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\SimpleExcel\SimpleExcelReader;
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
            $table->timestamp('dismissed_at')->nullable();
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

    public function test_excel_export_for_completed_recheck_includes_both_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now(),
            'connected_count' => 1,
            'disconnected_count' => 1,
            'total_count' => 2,
        ]);

        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'recovered.example',
            'sort_order' => 1,
            'check_status' => 'connected',
            'connected' => true,
            'status_code' => 'online',
            'message' => 'Agent online',
            'category' => 'Gaming',
        ]);
        DomainStatusCheckItem::query()->create([
            'domain_status_check_id' => $check->id,
            'domain' => 'still-down.example',
            'sort_order' => 2,
            'check_status' => 'disconnected',
            'connected' => false,
            'status_code' => 'domain_offline',
            'message' => 'No response',
            'category' => 'Gaming',
        ]);

        $response = $this->get(route('admin.domain.recheck-disconnected.export', $check->uuid));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->excelRows($response->streamedContent());
        $domains = collect($rows)->pluck('Domain');
        $statuses = collect($rows)->pluck('Status');

        $this->assertTrue($domains->contains('recovered.example'));
        $this->assertTrue($domains->contains('still-down.example'));
        $this->assertTrue($statuses->contains('Connected'));
        $this->assertTrue($statuses->contains('Disconnected'));
    }

    public function test_excel_export_is_refused_while_the_run_is_processing(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
        ]);

        $this->get(route('admin.domain.recheck-disconnected.export', $check->uuid))
            ->assertStatus(409);
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

    public function test_category_refresh_opens_that_category_run_not_a_newer_one(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $gaming = DomainCategory::query()->create(['name' => 'Gaming Refresh', 'slug' => 'gaming-refresh']);
        $news = DomainCategory::query()->create(['name' => 'News Refresh', 'slug' => 'news-refresh']);

        $gamingCheck = $this->makeDisconnectedCheck($admin->id, [
            'domain_category_id' => $gaming->id,
            'status' => 'completed',
            'completed_at' => now()->subHours(2),
            'connected_count' => 3,
            'disconnected_count' => 1,
        ]);
        $newsCheck = $this->makeDisconnectedCheck($admin->id, [
            'domain_category_id' => $news->id,
            'status' => 'completed',
            'completed_at' => now()->subHour(),
            'connected_count' => 4,
            'disconnected_count' => 2,
        ]);

        $this->get(route('admin.domain.recheck-disconnected', ['domain_category_id' => $gaming->id]))
            ->assertOk()
            ->assertSee('id="rdResults"', false)
            ->assertSee('Recheck campaigns')
            ->assertSee('data-resume-uuid="'.$gamingCheck->uuid.'"', false)
            ->assertSee('id="rdProgressCategory"', false)
            ->assertSee('Gaming Refresh')
            ->assertDontSee('data-resume-uuid="'.$newsCheck->uuid.'"', false);

        $this->get(route('admin.domain.recheck-disconnected.campaigns'))
            ->assertOk()
            ->assertSee('Gaming Refresh')
            ->assertSee('News Refresh');
    }

    public function test_campaigns_list_keeps_running_and_recent_finished_runs(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $expired = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHours(13),
        ]);
        $finished = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);
        $running = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
        ]);

        $this->get(route('admin.domain.recheck-disconnected.campaigns'))
            ->assertOk()
            ->assertSee('processing')
            ->assertSee($finished->uuid)
            ->assertSee($running->uuid)
            ->assertDontSee($expired->uuid);
    }

    public function test_preview_paginates_every_domain_and_rejects_other_checks(): void
    {
        $admin = $this->createAdmin();
        $other = Admin::query()->create([
            'name' => 'Other Admin',
            'slug' => 'other-preview-'.uniqid(),
            'email' => 'other-preview-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => Admin::ADMIN,
        ]);
        $this->actingAs($admin, 'admin');

        $check = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now(),
            'total_count' => 51,
        ]);

        foreach (range(1, 51) as $number) {
            DomainStatusCheckItem::query()->create([
                'domain_status_check_id' => $check->id,
                'domain' => sprintf('preview-domain-%02d.example', $number),
                'sort_order' => $number,
                'check_status' => $number === 1 ? 'connected' : 'disconnected',
                'category' => 'Gaming',
            ]);
        }

        $this->get(route('admin.domain.recheck-disconnected.campaigns.show', $check->uuid))
            ->assertOk()
            ->assertSee('preview-domain-01.example')
            ->assertSee('Connected')
            ->assertSee('Disconnected')
            ->assertDontSee('preview-domain-51.example');

        $this->get(route('admin.domain.recheck-disconnected.campaigns.show', [
            'uuid' => $check->uuid,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('preview-domain-51.example')
            ->assertDontSee('preview-domain-01.example');

        $otherCheck = $this->makeDisconnectedCheck($other->id, [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $manual = $this->makeDisconnectedCheck($admin->id, [
            'source' => 'manual',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->get(route('admin.domain.recheck-disconnected.campaigns.show', $otherCheck->uuid))
            ->assertNotFound();
        $this->get(route('admin.domain.recheck-disconnected.campaigns.show', $manual->uuid))
            ->assertNotFound();
        $this->get(route('admin.domain.recheck-disconnected.export', $otherCheck->uuid))
            ->assertNotFound();
        $this->get(route('admin.domain.recheck-disconnected.export', $manual->uuid))
            ->assertNotFound();
    }

    public function test_finished_recheck_older_than_retention_is_hidden(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $expired = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHours(13),
        ]);
        $missingCompletedAt = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => null,
        ]);
        DomainStatusCheck::query()->whereKey($missingCompletedAt->id)->update([
            'updated_at' => now()->subHours(13),
        ]);
        $stillRunning = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
            'created_at' => now()->subHours(13),
        ]);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertDontSee($expired->uuid)
            ->assertDontSee($missingCompletedAt->uuid)
            ->assertSee('data-resume-uuid="'.$stillRunning->uuid.'"', false);
    }

    public function test_check_query_ignores_another_admin_and_manual_runs(): void
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

        $otherCheck = $this->makeDisconnectedCheck($other->id, [
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);
        $manual = $this->makeDisconnectedCheck($admin->id, [
            'source' => 'manual',
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);

        $this->get(route('admin.domain.recheck-disconnected', ['check' => $otherCheck->uuid]))
            ->assertOk()
            ->assertDontSee($otherCheck->uuid)
            ->assertDontSee('id="rdResumeBootstrap"', false);

        $this->get(route('admin.domain.recheck-disconnected', ['check' => $manual->uuid]))
            ->assertOk()
            ->assertDontSee($manual->uuid)
            ->assertDontSee('id="rdResumeBootstrap"', false);
    }

    public function test_cleanup_deletes_expired_disconnected_runs_only(): void
    {
        $admin = $this->createAdmin();

        $expired = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHours(13),
        ]);
        $recent = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);
        $manual = $this->makeDisconnectedCheck($admin->id, [
            'source' => 'manual',
            'status' => 'completed',
            'completed_at' => now()->subHours(13),
        ]);
        $running = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'processing',
            'completed_at' => null,
        ]);

        app(DomainStatusCheckCleanupService::class)->runBatchedCleanup($admin->id);

        $this->assertNull(DomainStatusCheck::query()->find($expired->id));
        $this->assertNotNull(DomainStatusCheck::query()->find($recent->id));
        $this->assertNull(DomainStatusCheck::query()->find($manual->id));
        $this->assertNotNull(DomainStatusCheck::query()->find($running->id));
    }

    public function test_clear_hides_a_finished_run_and_does_not_paint_an_older_one(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $older = $this->makeDisconnectedCheck($admin->id, [
            'status_message' => 'Older recheck',
            'completed_at' => now()->subHours(2),
        ]);
        $newer = $this->makeDisconnectedCheck($admin->id, [
            'status_message' => 'Newer recheck',
            'completed_at' => now()->subMinutes(10),
        ]);

        $this->postJson(route('admin.domain.recheck-disconnected.clear', $newer->uuid))
            ->assertOk()
            ->assertJsonPath('cleared', true);

        $this->assertNotNull($newer->fresh()->dismissed_at);
        $this->assertNull($older->fresh()->dismissed_at);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertDontSee('data-resume-uuid="'.$newer->uuid.'"', false)
            ->assertDontSee('data-resume-uuid="'.$older->uuid.'"', false)
            ->assertDontSee('id="rdResumeBootstrap"', false)
            ->assertSee('id="rdSummaryTotal">0</', false);

        $this->get(route('admin.domain.recheck-disconnected', ['check' => $newer->uuid]))
            ->assertOk()
            ->assertDontSee('id="rdResumeBootstrap"', false);

        $this->get(route('admin.domain.recheck-disconnected.campaigns'))
            ->assertOk()
            ->assertSee($newer->uuid)
            ->assertSee($older->uuid);
    }

    public function test_clearing_one_category_keeps_the_other_category_run(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $gaming = DomainCategory::query()->create(['name' => 'Gaming Clear', 'slug' => 'gaming-clear']);
        $news = DomainCategory::query()->create(['name' => 'News Clear', 'slug' => 'news-clear']);

        $gamingCheck = $this->makeDisconnectedCheck($admin->id, [
            'domain_category_id' => $gaming->id,
            'completed_at' => now()->subHours(2),
        ]);
        $newsCheck = $this->makeDisconnectedCheck($admin->id, [
            'domain_category_id' => $news->id,
            'completed_at' => now()->subHour(),
        ]);

        $this->postJson(route('admin.domain.recheck-disconnected.clear', $gamingCheck->uuid))
            ->assertOk()
            ->assertJsonPath('cleared', true);

        $this->get(route('admin.domain.recheck-disconnected', ['domain_category_id' => $gaming->id]))
            ->assertOk()
            ->assertDontSee('data-resume-uuid="'.$gamingCheck->uuid.'"', false)
            ->assertDontSee('data-resume-uuid="'.$newsCheck->uuid.'"', false);

        $this->get(route('admin.domain.recheck-disconnected', ['domain_category_id' => $news->id]))
            ->assertOk()
            ->assertSee('data-resume-uuid="'.$newsCheck->uuid.'"', false)
            ->assertDontSee('data-resume-uuid="'.$gamingCheck->uuid.'"', false);
    }

    public function test_clear_refuses_a_running_recheck(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $check = $this->makeDisconnectedCheck($admin->id, [
            'status' => 'processing',
            'phase' => 'initial',
            'completed_at' => null,
            'status_message' => 'Still checking',
        ]);

        $this->postJson(route('admin.domain.recheck-disconnected.clear', $check->uuid))
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertNull($check->fresh()->dismissed_at);

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertSee('data-resume-uuid="'.$check->uuid.'"', false);

        $check->forceFill(['dismissed_at' => now()])->save();

        $this->get(route('admin.domain.recheck-disconnected'))
            ->assertOk()
            ->assertSee('data-resume-uuid="'.$check->uuid.'"', false);
    }

    public function test_clear_is_not_available_to_another_admin(): void
    {
        $admin = $this->createAdmin();
        $other = Admin::query()->create([
            'name' => 'Other Admin',
            'slug' => 'other-clear-'.uniqid(),
            'email' => 'other-clear-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => Admin::ADMIN,
        ]);
        $this->actingAs($admin, 'admin');

        $otherCheck = $this->makeDisconnectedCheck($other->id);

        $this->postJson(route('admin.domain.recheck-disconnected.clear', $otherCheck->uuid))
            ->assertNotFound();

        $this->assertNull($otherCheck->fresh()->dismissed_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDisconnectedCheck(int $adminId, array $overrides = []): DomainStatusCheck
    {
        return DomainStatusCheck::query()->create(array_merge([
            'admin_id' => $adminId,
            'source' => 'disconnected',
            'status' => 'completed',
            'phase' => 'done',
            'total_count' => 1,
            'processed_count' => 1,
            'connected_count' => 1,
            'disconnected_count' => 0,
            'in_inventory_count' => 1,
            'inventory_updated_count' => 0,
            'update_inventory' => true,
            'use_authenticated_check' => true,
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
        $path = tempnam(sys_get_temp_dir(), 'recheck-xlsx');
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
