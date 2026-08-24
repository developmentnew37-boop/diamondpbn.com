<?php

namespace Tests\Unit;

use App\Jobs\BulkRetryScheduleCampaignPostsJob;
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

class ScheduleCampaignCounterDriftTest extends TestCase
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
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
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
        // Case A: overshoot → semi_failed (not queued)
        $this->assertSame(
            'semi_failed',
            $this->counters->deriveStatusFromCounters(450, 444, 31, 0)
        );

        // Case B: completed === total but failed > 0 → semi_failed (not completed)
        $this->assertSame(
            'semi_failed',
            $this->counters->deriveStatusFromCounters(100, 100, 5, 0)
        );

        $this->assertSame('completed', $this->counters->deriveStatusFromCounters(100, 100, 0, 0));
        $this->assertSame('running', $this->counters->deriveStatusFromCounters(100, 50, 0, 50));
        $this->assertSame('queued', $this->counters->deriveStatusFromCounters(100, 0, 0, 100));
        $this->assertSame('failed', $this->counters->deriveStatusFromCounters(10, 0, 10, 0));
    }

    public function test_sync_campaign_from_posts_heals_drifted_counters(): void
    {
        $adminId = $this->adminId();
        $campaign = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-DRIFT',
            'admin_id' => $adminId,
            'status' => 'queued',
            'total_targets' => 3,
            'completed_targets' => 3,
            'failed_targets' => 2, // drifted
            'report_token' => Str::random(64),
        ]);

        ScheduleCampaignPost::query()->insert([
            ['schedule_campaign_id' => $campaign->id, 'status' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['schedule_campaign_id' => $campaign->id, 'status' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['schedule_campaign_id' => $campaign->id, 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $fresh = $this->counters->syncCampaignFromPosts($campaign);

        $this->assertSame(3, (int) $fresh->total_targets);
        $this->assertSame(2, (int) $fresh->completed_targets);
        $this->assertSame(1, (int) $fresh->failed_targets);
        $this->assertSame('semi_failed', $fresh->status);
    }

    public function test_account_for_failed_retries_decrements_and_reopens(): void
    {
        $adminId = $this->adminId();
        $campaign = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-RETRY',
            'admin_id' => $adminId,
            'status' => 'semi_failed',
            'total_targets' => 10,
            'completed_targets' => 7,
            'failed_targets' => 3,
            'finished_at' => now(),
            'report_token' => Str::random(64),
        ]);

        $fresh = $this->counters->accountForFailedPostRetries($campaign, 2);

        $this->assertSame(1, (int) $fresh->failed_targets);
        $this->assertSame('running', $fresh->status);
        $this->assertNull($fresh->finished_at);
    }

    public function test_bulk_retry_decrements_failed_via_sync(): void
    {
        Queue::fake();

        $adminId = $this->adminId();
        $campaign = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-BULK',
            'admin_id' => $adminId,
            'status' => 'semi_failed',
            'total_targets' => 3,
            'completed_targets' => 1,
            'failed_targets' => 5, // drifted high
            'finished_at' => now(),
            'report_token' => Str::random(64),
        ]);

        $failedIds = [];
        for ($i = 0; $i < 2; $i++) {
            $failedIds[] = ScheduleCampaignPost::query()->insertGetId([
                'schedule_campaign_id' => $campaign->id,
                'status' => 'failed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        ScheduleCampaignPost::query()->insert([
            'schedule_campaign_id' => $campaign->id,
            'status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryScheduleCampaignPostsJob([$campaign->id]))->handle($this->counters);

        $fresh = $campaign->fresh();
        $this->assertSame(1, (int) $fresh->completed_targets);
        $this->assertSame(0, (int) $fresh->failed_targets); // both failed requeued
        $this->assertSame('running', $fresh->status);

        Queue::assertPushed(PublishScheduledCampaignPostJob::class, 2);

        foreach ($failedIds as $id) {
            $this->assertSame('queued', ScheduleCampaignPost::query()->find($id)->status);
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
