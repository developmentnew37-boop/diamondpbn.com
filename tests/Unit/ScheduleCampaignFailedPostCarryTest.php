<?php

namespace Tests\Unit;

use App\Jobs\BulkRetryScheduleCampaignPostsJob;
use App\Models\Admin;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\ScheduleCampaignFailedPostCarryService;
use App\Services\ScheduleCampaignTargetCounterService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleCampaignFailedPostCarryTest extends TestCase
{
    private ScheduleCampaignFailedPostCarryService $carry;

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
            'campaign.schedule.failed_carry_grace_days' => 2,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->counters = new ScheduleCampaignTargetCounterService;
        $this->carry = new ScheduleCampaignFailedPostCarryService($this->counters);
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

    public function test_carry_requeues_failed_post_inside_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'semi_failed', 1, 1);
        $postId = $this->makeFailedPost($campaign->id, '2026-08-12 00:00:00', attemptCount: 5);

        $summary = $this->carry->carryFailedPosts(Carbon::parse('2026-08-15'));

        $this->assertSame(1, $summary['campaigns']);
        $this->assertSame(1, $summary['posts']);

        $post = ScheduleCampaignPost::query()->find($postId);
        $this->assertSame('queued', $post->status);
        $this->assertSame(0, (int) $post->attempt_count);
        $this->assertTrue($post->schedule_at->eq(Carbon::parse('2026-08-15 00:00:00')));

        $fresh = $campaign->fresh();
        $this->assertSame(0, (int) $fresh->failed_targets);
        $this->assertSame('queued', $fresh->status);
        $this->assertNull($fresh->finished_at);

        Carbon::setTestNow();
    }

    public function test_carry_still_runs_on_grace_day_two(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00:00'));

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'failed', 0, 1);
        $postId = $this->makeFailedPost($campaign->id, '2026-08-19 00:00:00', attemptCount: 5);

        $summary = $this->carry->carryFailedPosts(Carbon::parse('2026-08-22'));

        $this->assertSame(1, $summary['posts']);
        $this->assertSame('queued', ScheduleCampaignPost::query()->find($postId)->status);

        Carbon::setTestNow();
    }

    public function test_carry_skips_after_grace_ends(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-23 08:00:00'));

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'failed', 0, 1);
        $postId = $this->makeFailedPost($campaign->id, '2026-08-19 00:00:00', attemptCount: 5);

        $summary = $this->carry->carryFailedPosts(Carbon::parse('2026-08-23'));

        $this->assertSame(0, $summary['campaigns']);
        $this->assertSame(0, $summary['posts']);
        $this->assertSame('failed', ScheduleCampaignPost::query()->find($postId)->status);

        Carbon::setTestNow();
    }

    public function test_carry_skips_paused_campaign(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:00:00'));

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'paused', 0, 1);
        $postId = $this->makeFailedPost($campaign->id, '2026-08-12 00:00:00');

        $summary = $this->carry->carryFailedPosts(Carbon::parse('2026-08-15'));

        $this->assertSame(0, $summary['posts']);
        $this->assertSame('failed', ScheduleCampaignPost::query()->find($postId)->status);

        Carbon::setTestNow();
    }

    public function test_carry_skips_cancelled_campaign(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:00:00'));

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'cancelled', 0, 1);
        $postId = $this->makeFailedPost($campaign->id, '2026-08-12 00:00:00');

        $summary = $this->carry->carryFailedPosts(Carbon::parse('2026-08-15'));

        $this->assertSame(0, $summary['posts']);
        $this->assertSame('failed', ScheduleCampaignPost::query()->find($postId)->status);

        Carbon::setTestNow();
    }

    public function test_carry_reopens_semi_failed_to_running_when_successes_exist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'semi_failed', 1, 1);
        ScheduleCampaignPost::query()->insert([
            'schedule_campaign_id' => $campaign->id,
            'status' => 'success',
            'attempt_count' => 0,
            'schedule_at' => '2026-08-12 00:00:00',
            'is_converted_live' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeFailedPost($campaign->id, '2026-08-13 00:00:00');

        $this->carry->carryFailedPosts(Carbon::parse('2026-08-15'));

        $fresh = $campaign->fresh();
        $this->assertSame(0, (int) $fresh->failed_targets);
        $this->assertSame(1, (int) $fresh->completed_targets);
        $this->assertSame('running', $fresh->status);
        $this->assertNull($fresh->finished_at);

        Carbon::setTestNow();
    }

    public function test_is_within_grace_window(): void
    {
        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'semi_failed', 1, 1);

        $this->assertTrue($this->carry->isWithinGraceWindow($campaign, Carbon::parse('2026-08-20')));
        $this->assertTrue($this->carry->isWithinGraceWindow($campaign, Carbon::parse('2026-08-22')));
        $this->assertFalse($this->carry->isWithinGraceWindow($campaign, Carbon::parse('2026-08-23')));
    }

    public function test_bulk_retry_resets_attempt_count(): void
    {
        Queue::fake();

        $campaign = $this->makeCampaign('2026-08-12', '2026-08-20', 'semi_failed', 0, 1);
        $postId = $this->makeFailedPost($campaign->id, '2026-08-12 00:00:00', attemptCount: 5);

        (new BulkRetryScheduleCampaignPostsJob([$campaign->id]))->handle($this->counters);

        $post = ScheduleCampaignPost::query()->find($postId);
        $this->assertSame('queued', $post->status);
        $this->assertSame(0, (int) $post->attempt_count);
    }

    private function makeCampaign(
        string $from,
        string $to,
        string $status,
        int $completed,
        int $failed,
    ): ScheduleCampaign {
        $total = $completed + $failed;

        return ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-CARRY-'.Str::random(4),
            'admin_id' => $this->adminId(),
            'status' => $status,
            'total_targets' => $total,
            'completed_targets' => $completed,
            'failed_targets' => $failed,
            'schedule_from_date' => $from,
            'schedule_to_date' => $to,
            'finished_at' => in_array($status, ['completed', 'semi_failed', 'failed'], true) ? now() : null,
            'report_token' => Str::random(64),
        ]);
    }

    private function makeFailedPost(int $campaignId, string $scheduleAt, int $attemptCount = 5): int
    {
        return (int) ScheduleCampaignPost::query()->insertGetId([
            'schedule_campaign_id' => $campaignId,
            'status' => 'failed',
            'attempt_count' => $attemptCount,
            'schedule_at' => $scheduleAt,
            'is_converted_live' => false,
            'last_error' => 'domain down',
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
