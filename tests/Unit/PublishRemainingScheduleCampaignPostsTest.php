<?php

namespace Tests\Unit;

use App\Jobs\PublishRemainingScheduleCampaignPostsJob;
use App\Jobs\PublishScheduledCampaignPostJob;
use App\Models\Admin;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\ScheduleCampaignTargetCounterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublishRemainingScheduleCampaignPostsTest extends TestCase
{
    private ScheduleCampaignTargetCounterService $counters;

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
        $this->counters = new ScheduleCampaignTargetCounterService;
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

        Schema::create('schedule_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->date('schedule_from_date')->nullable();
            $table->date('schedule_to_date')->nullable();
            $table->unsignedBigInteger('converted_from_campaign_id')->nullable();
            $table->string('conversion_pipeline_status')->nullable();
            $table->string('report_token', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('schedule_campaign_domain_id')->nullable();
            $table->unsignedBigInteger('schedule_campaign_article_id')->nullable();
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

    public function test_publish_remaining_keeps_future_schedule_at_and_dispatches(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('running', 1, 0, 2);
        $successId = $this->insertPost($campaign->id, 'success', '2026-08-12 00:00:00');
        $futureA = $this->insertPost($campaign->id, 'queued', '2026-08-14 00:00:00');
        $futureB = $this->insertPost($campaign->id, 'queued', '2026-08-15 00:00:00');

        $summary = PublishRemainingScheduleCampaignPostsJob::publishRemaining($campaign->id, $this->counters);

        $this->assertSame(2, $summary['queued']);
        $this->assertFalse($summary['skipped']);

        $this->assertSame('2026-08-14 00:00:00', ScheduleCampaignPost::find($futureA)->schedule_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 00:00:00', ScheduleCampaignPost::find($futureB)->schedule_at->format('Y-m-d H:i:s'));
        $this->assertSame('success', ScheduleCampaignPost::find($successId)->status);

        Queue::assertPushed(PublishScheduledCampaignPostJob::class, 2);
    }

    public function test_publish_remaining_resets_failed_and_counters(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('semi_failed', 1, 1, 2);
        $this->insertPost($campaign->id, 'success', '2026-08-12 00:00:00');
        $failedId = $this->insertPost($campaign->id, 'failed', '2026-08-14 00:00:00', attemptCount: 5, lastError: 'down');

        $summary = PublishRemainingScheduleCampaignPostsJob::publishRemaining($campaign->id, $this->counters);

        $this->assertSame(1, $summary['queued']);

        $failed = ScheduleCampaignPost::find($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertSame(0, (int) $failed->attempt_count);
        $this->assertNull($failed->last_error);
        $this->assertSame('2026-08-14 00:00:00', $failed->schedule_at->format('Y-m-d H:i:s'));

        $fresh = $campaign->fresh();
        $this->assertSame(0, (int) $fresh->failed_targets);
        $this->assertSame('running', $fresh->status);

        Queue::assertPushed(PublishScheduledCampaignPostJob::class, 1);
    }

    public function test_publish_remaining_skips_paused_campaign(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('paused', 0, 0, 1);
        $postId = $this->insertPost($campaign->id, 'queued', '2026-08-14 00:00:00');

        $summary = PublishRemainingScheduleCampaignPostsJob::publishRemaining($campaign->id, $this->counters);

        $this->assertTrue($summary['skipped']);
        $this->assertSame('paused_or_cancelled', $summary['reason']);
        $this->assertSame(0, $summary['queued']);
        $this->assertSame('queued', ScheduleCampaignPost::find($postId)->status);

        Queue::assertNothingPushed();
    }

    public function test_publish_remaining_does_not_touch_success_or_converted_live(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('running', 1, 0, 2);
        $this->insertPost($campaign->id, 'success', '2026-08-12 00:00:00');
        ScheduleCampaignPost::query()->insertGetId([
            'schedule_campaign_id' => $campaign->id,
            'status' => 'queued',
            'attempt_count' => 0,
            'schedule_at' => '2026-08-14 00:00:00',
            'is_converted_live' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = PublishRemainingScheduleCampaignPostsJob::publishRemaining($campaign->id, $this->counters);

        $this->assertTrue($summary['skipped']);
        $this->assertSame('none_remaining', $summary['reason']);
        Queue::assertNothingPushed();
    }

    private function makeCampaign(string $status, int $completed, int $failed, int $total): ScheduleCampaign
    {
        return ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-REM-'.Str::random(4),
            'admin_id' => $this->adminId(),
            'status' => $status,
            'total_targets' => $total,
            'completed_targets' => $completed,
            'failed_targets' => $failed,
            'schedule_from_date' => '2026-08-11',
            'schedule_to_date' => '2026-08-15',
            'finished_at' => in_array($status, ['completed', 'semi_failed', 'failed'], true) ? now() : null,
            'report_token' => Str::random(64),
        ]);
    }

    private function insertPost(
        int $campaignId,
        string $status,
        string $scheduleAt,
        int $attemptCount = 0,
        ?string $lastError = null,
    ): int {
        return (int) ScheduleCampaignPost::query()->insertGetId([
            'schedule_campaign_id' => $campaignId,
            'status' => $status,
            'attempt_count' => $attemptCount,
            'schedule_at' => $scheduleAt,
            'is_converted_live' => false,
            'last_error' => $lastError,
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
