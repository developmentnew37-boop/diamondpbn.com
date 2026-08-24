<?php

namespace Tests\Unit;

use App\Jobs\BulkRetryScheduleSidebarCampaignTasksJob;
use App\Jobs\PublishScheduledSidebarBlogrollJob;
use App\Models\Admin;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleSidebarCampaignCounterDriftTest extends TestCase
{
    private ScheduleSidebarCampaignTargetCounterService $counters;

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
        $this->counters = new ScheduleSidebarCampaignTargetCounterService;
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('type');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->unsignedBigInteger('converted_from_sidebar_campaign_id')->nullable();
            $table->string('conversion_pipeline_status')->nullable();
            $table->string('report_token', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->unsignedBigInteger('schedule_sidebar_campaign_domain_id')->nullable();
            $table->unsignedBigInteger('schedule_sidebar_campaign_link_id')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });
    }

    public function test_derive_status_handles_overshoot_and_completed_with_failures(): void
    {
        $this->assertSame(
            'semi_failed',
            $this->counters->deriveStatusFromCounters(450, 444, 31, 0)
        );

        $this->assertSame(
            'semi_failed',
            $this->counters->deriveStatusFromCounters(100, 100, 5, 0)
        );

        $this->assertSame('completed', $this->counters->deriveStatusFromCounters(100, 100, 0, 0));
        $this->assertSame('running', $this->counters->deriveStatusFromCounters(100, 50, 0, 50));
        $this->assertSame('queued', $this->counters->deriveStatusFromCounters(100, 0, 0, 100));
        $this->assertSame('failed', $this->counters->deriveStatusFromCounters(10, 0, 10, 0));
    }

    public function test_sync_campaign_from_tasks_heals_drifted_counters(): void
    {
        $adminId = $this->adminId();
        $campaign = ScheduleSidebarCampaign::query()->create([
            'campaign_no' => 'SS-DRIFT',
            'admin_id' => $adminId,
            'status' => 'queued',
            'total_targets' => 3,
            'completed_targets' => 3,
            'failed_targets' => 2,
            'report_token' => Str::random(64),
        ]);

        ScheduleSidebarCampaignTask::query()->insert([
            ['schedule_sidebar_campaign_id' => $campaign->id, 'status' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['schedule_sidebar_campaign_id' => $campaign->id, 'status' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['schedule_sidebar_campaign_id' => $campaign->id, 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $fresh = $this->counters->syncCampaignFromTasks($campaign);

        $this->assertSame(3, (int) $fresh->total_targets);
        $this->assertSame(2, (int) $fresh->completed_targets);
        $this->assertSame(1, (int) $fresh->failed_targets);
        $this->assertSame('semi_failed', $fresh->status);
    }

    public function test_account_for_failed_retries_decrements_and_reopens(): void
    {
        $adminId = $this->adminId();
        $campaign = ScheduleSidebarCampaign::query()->create([
            'campaign_no' => 'SS-RETRY',
            'admin_id' => $adminId,
            'status' => 'semi_failed',
            'total_targets' => 10,
            'completed_targets' => 7,
            'failed_targets' => 3,
            'finished_at' => now(),
            'report_token' => Str::random(64),
        ]);

        $fresh = $this->counters->accountForFailedTaskRetries($campaign, 2);

        $this->assertSame(1, (int) $fresh->failed_targets);
        $this->assertSame('running', $fresh->status);
        $this->assertNull($fresh->finished_at);
    }

    public function test_bulk_retry_decrements_failed_via_sync(): void
    {
        Queue::fake();

        $adminId = $this->adminId();
        $campaign = ScheduleSidebarCampaign::query()->create([
            'campaign_no' => 'SS-BULK',
            'admin_id' => $adminId,
            'status' => 'semi_failed',
            'total_targets' => 3,
            'completed_targets' => 1,
            'failed_targets' => 5,
            'finished_at' => now(),
            'report_token' => Str::random(64),
        ]);

        $failedIds = [];
        for ($i = 0; $i < 2; $i++) {
            $failedIds[] = ScheduleSidebarCampaignTask::query()->insertGetId([
                'schedule_sidebar_campaign_id' => $campaign->id,
                'status' => 'failed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        ScheduleSidebarCampaignTask::query()->insert([
            'schedule_sidebar_campaign_id' => $campaign->id,
            'status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryScheduleSidebarCampaignTasksJob([$campaign->id]))->handle($this->counters);

        $fresh = $campaign->fresh();
        $this->assertSame(1, (int) $fresh->completed_targets);
        $this->assertSame(0, (int) $fresh->failed_targets);
        $this->assertSame('running', $fresh->status);

        Queue::assertPushed(PublishScheduledSidebarBlogrollJob::class, 2);

        foreach ($failedIds as $id) {
            $this->assertSame('queued', ScheduleSidebarCampaignTask::query()->find($id)->status);
        }
    }

    private function adminId(): int
    {
        return (int) DB::table('admins')->insertGetId([
            'name' => 'Owner',
            'slug' => 'owner',
            'email' => 'owner-'.Str::random(6).'@example.test',
            'password' => bcrypt('password'),
            'type' => Admin::ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
