<?php

namespace Tests\Unit;

use App\Jobs\ApplyConvertedPostScheduleJob;
use App\Jobs\BulkRetryScheduleCampaignPostsJob;
use App\Jobs\DraftConvertedLivePostsJob;
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

class BulkRetryConvertedScheduleCampaignTest extends TestCase
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
            $table->timestamp('schedule_at')->nullable();
            $table->string('status')->default('queued');
            $table->string('conversion_phase')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->boolean('is_converted_live')->nullable()->default(false);
            $table->text('last_error')->nullable();
            $table->text('last_conversion_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });
    }

    public function test_schedule_list_query_excludes_converted_campaigns(): void
    {
        $adminId = $this->adminId();

        $normal = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-NORMAL',
            'admin_id' => $adminId,
            'status' => 'queued',
            'is_sticky_campaign' => false,
            'converted_from_campaign_id' => null,
            'report_token' => Str::random(64),
        ]);

        ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-CONV',
            'admin_id' => $adminId,
            'status' => 'semi_failed',
            'is_sticky_campaign' => false,
            'converted_from_campaign_id' => 99,
            'report_token' => Str::random(64),
        ]);

        $ids = ScheduleCampaign::query()
            ->where('is_sticky_campaign', false)
            ->whereNull('converted_from_campaign_id')
            ->pluck('id')
            ->all();

        $this->assertSame([(int) $normal->id], array_map('intval', $ids));
    }

    public function test_bulk_retry_converted_uses_draft_job_not_publish_scheduled(): void
    {
        Queue::fake();

        $campaign = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-CONV-RETRY',
            'admin_id' => $this->adminId(),
            'status' => 'semi_failed',
            'total_targets' => 1,
            'completed_targets' => 0,
            'failed_targets' => 1,
            'converted_from_campaign_id' => 42,
            'finished_at' => now(),
            'report_token' => Str::random(64),
        ]);

        $postId = (int) ScheduleCampaignPost::query()->insertGetId([
            'schedule_campaign_id' => $campaign->id,
            'status' => 'failed',
            'conversion_phase' => 'failed',
            'attempt_count' => 3,
            'is_converted_live' => true,
            'last_error' => 'down',
            'last_conversion_error' => 'down',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryScheduleCampaignPostsJob([$campaign->id]))->handle($this->counters);

        $post = ScheduleCampaignPost::query()->find($postId);
        $this->assertSame('queued', $post->status);
        $this->assertSame('pending_draft', $post->conversion_phase);
        $this->assertSame(0, (int) $post->attempt_count);

        Queue::assertPushed(DraftConvertedLivePostsJob::class, 1);
        Queue::assertNotPushed(PublishScheduledCampaignPostJob::class);
        Queue::assertNotPushed(ApplyConvertedPostScheduleJob::class);
    }

    public function test_bulk_retry_converted_apply_phase_uses_apply_job(): void
    {
        Queue::fake();

        $campaign = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-CONV-APPLY',
            'admin_id' => $this->adminId(),
            'status' => 'semi_failed',
            'total_targets' => 1,
            'completed_targets' => 0,
            'failed_targets' => 1,
            'converted_from_campaign_id' => 42,
            'report_token' => Str::random(64),
        ]);

        $postId = (int) ScheduleCampaignPost::query()->insertGetId([
            'schedule_campaign_id' => $campaign->id,
            'status' => 'failed',
            'conversion_phase' => 'scheduled_publish',
            'attempt_count' => 2,
            'is_converted_live' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryScheduleCampaignPostsJob([$campaign->id]))->handle($this->counters);

        $post = ScheduleCampaignPost::query()->find($postId);
        $this->assertSame('queued', $post->status);
        $this->assertSame('drafted', $post->conversion_phase);

        Queue::assertPushed(ApplyConvertedPostScheduleJob::class, 1);
        Queue::assertNotPushed(DraftConvertedLivePostsJob::class);
        Queue::assertNotPushed(PublishScheduledCampaignPostJob::class);
    }

    public function test_bulk_retry_normal_still_uses_publish_scheduled(): void
    {
        Queue::fake();

        $campaign = ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-NORMAL-RETRY',
            'admin_id' => $this->adminId(),
            'status' => 'semi_failed',
            'total_targets' => 1,
            'completed_targets' => 0,
            'failed_targets' => 1,
            'converted_from_campaign_id' => null,
            'report_token' => Str::random(64),
        ]);

        $postId = (int) ScheduleCampaignPost::query()->insertGetId([
            'schedule_campaign_id' => $campaign->id,
            'status' => 'failed',
            'attempt_count' => 5,
            'is_converted_live' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryScheduleCampaignPostsJob([$campaign->id]))->handle($this->counters);

        $this->assertSame('queued', ScheduleCampaignPost::query()->find($postId)->status);
        Queue::assertPushed(PublishScheduledCampaignPostJob::class, 1);
        Queue::assertNotPushed(DraftConvertedLivePostsJob::class);
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
