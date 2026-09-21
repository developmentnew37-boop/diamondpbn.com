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

class BulkRetryScheduleSidebarIncludeStuckTest extends TestCase
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
            $table->date('schedule_from_date')->nullable();
            $table->date('schedule_to_date')->nullable();
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
            $table->timestamp('schedule_at')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->boolean('is_converted_live')->nullable()->default(false);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });
    }

    public function test_failed_only_does_not_touch_queued_or_success(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('semi_failed', 1, 1, 3);
        $successId = $this->insertTask($campaign->id, 'success', generation: 1);
        $queuedId = $this->insertTask($campaign->id, 'queued', generation: 2);
        $failedId = $this->insertTask($campaign->id, 'failed', generation: 3, lastError: 'down');

        $this->assertSame(1, BulkRetryScheduleSidebarCampaignTasksJob::retryableQuery((int) $campaign->id, false)->count());
        $this->assertSame(2, BulkRetryScheduleSidebarCampaignTasksJob::retryableQuery((int) $campaign->id, true)->count());

        (new BulkRetryScheduleSidebarCampaignTasksJob([(int) $campaign->id]))->handle($this->counters);

        $this->assertSame('success', ScheduleSidebarCampaignTask::find($successId)->status);
        $this->assertSame(2, (int) ScheduleSidebarCampaignTask::find($queuedId)->dispatch_generation);
        $this->assertSame('queued', ScheduleSidebarCampaignTask::find($queuedId)->status);

        $failed = ScheduleSidebarCampaignTask::find($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertNull($failed->last_error);
        $this->assertSame(0, (int) $failed->attempt_count);
        $this->assertSame(3, (int) $failed->dispatch_generation);

        Queue::assertPushed(PublishScheduledSidebarBlogrollJob::class, 1);
    }

    public function test_include_stuck_retries_failed_queued_and_publishing_and_bumps_generation(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('semi_failed', 1, 1, 4);
        $successId = $this->insertTask($campaign->id, 'success', generation: 1);
        $queuedId = $this->insertTask($campaign->id, 'queued', generation: 4);
        $publishingId = $this->insertTask($campaign->id, 'publishing', generation: 5, lockedAt: now());
        $failedId = $this->insertTask($campaign->id, 'failed', generation: 6, lastError: 'down');

        (new BulkRetryScheduleSidebarCampaignTasksJob([(int) $campaign->id], true))->handle($this->counters);

        $this->assertSame('success', ScheduleSidebarCampaignTask::find($successId)->status);
        $this->assertSame(1, (int) ScheduleSidebarCampaignTask::find($successId)->dispatch_generation);

        $queued = ScheduleSidebarCampaignTask::find($queuedId);
        $this->assertSame('queued', $queued->status);
        $this->assertSame(5, (int) $queued->dispatch_generation);

        $publishing = ScheduleSidebarCampaignTask::find($publishingId);
        $this->assertSame('queued', $publishing->status);
        $this->assertNull($publishing->locked_at);
        $this->assertSame(6, (int) $publishing->dispatch_generation);

        $failed = ScheduleSidebarCampaignTask::find($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertNull($failed->last_error);
        $this->assertSame(0, (int) $failed->attempt_count);
        $this->assertSame(7, (int) $failed->dispatch_generation);

        Queue::assertPushed(PublishScheduledSidebarBlogrollJob::class, 3);
    }

    public function test_include_stuck_skips_converted_live_tasks(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('running', 0, 0, 1);
        ScheduleSidebarCampaignTask::query()->insertGetId([
            'schedule_sidebar_campaign_id' => $campaign->id,
            'status' => 'queued',
            'attempt_count' => 0,
            'dispatch_generation' => 1,
            'schedule_at' => '2026-08-14 00:00:00',
            'is_converted_live' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, BulkRetryScheduleSidebarCampaignTasksJob::retryableQuery((int) $campaign->id, true)->count());

        (new BulkRetryScheduleSidebarCampaignTasksJob([(int) $campaign->id], true))->handle($this->counters);

        Queue::assertNothingPushed();
    }

    public function test_include_stuck_skips_paused_campaign(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('paused', 0, 1, 1);
        $this->insertTask($campaign->id, 'failed', generation: 1, lastError: 'down');

        (new BulkRetryScheduleSidebarCampaignTasksJob([(int) $campaign->id], true))->handle($this->counters);

        Queue::assertNothingPushed();
        $this->assertSame('failed', ScheduleSidebarCampaignTask::query()->first()->status);
    }

    private function makeCampaign(string $status, int $completed, int $failed, int $total): ScheduleSidebarCampaign
    {
        return ScheduleSidebarCampaign::query()->create([
            'campaign_no' => 'SSC-RETRY-'.Str::random(4),
            'admin_id' => $this->adminId(),
            'status' => $status,
            'total_targets' => $total,
            'completed_targets' => $completed,
            'failed_targets' => $failed,
            'schedule_from_date' => '2026-08-11',
            'schedule_to_date' => '2026-08-15',
            'finished_at' => in_array($status, ['completed', 'semi_failed', 'failed'], true) ? now() : null,
        ]);
    }

    private function insertTask(
        int $campaignId,
        string $status,
        int $generation = 0,
        ?string $lastError = null,
        mixed $lockedAt = null,
    ): int {
        return (int) ScheduleSidebarCampaignTask::query()->insertGetId([
            'schedule_sidebar_campaign_id' => $campaignId,
            'status' => $status,
            'attempt_count' => $status === 'failed' ? 5 : 0,
            'dispatch_generation' => $generation,
            'schedule_at' => '2026-08-14 00:00:00',
            'is_converted_live' => false,
            'last_error' => $lastError,
            'locked_at' => $lockedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
