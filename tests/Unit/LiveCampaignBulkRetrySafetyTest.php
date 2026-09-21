<?php

namespace Tests\Unit;

use App\Jobs\BulkRetryHiddenLinksCampaignTasksJob;
use App\Jobs\BulkRetrySidebarCampaignTasksJob;
use App\Jobs\PublishCampaignPostJob;
use App\Jobs\PublishHiddenLinksJob;
use App\Jobs\PublishSidebarBlogrollJob;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\SidebarCampaignTask;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveCampaignBulkRetrySafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);
    }

    public function test_post_duplicate_dispatch_same_generation_is_dropped(): void
    {
        Queue::fake();

        PublishCampaignPostJob::dispatch(10, 3)->onQueue('campaigns');
        PublishCampaignPostJob::dispatch(10, 3)->onQueue('campaigns');

        Queue::assertPushed(PublishCampaignPostJob::class, 1);
    }

    public function test_post_new_generation_is_not_dropped_as_duplicate(): void
    {
        Queue::fake();

        PublishCampaignPostJob::dispatch(10, 3)->onQueue('campaigns');
        PublishCampaignPostJob::dispatch(10, 4)->onQueue('campaigns');

        Queue::assertPushed(PublishCampaignPostJob::class, 2);
    }

    public function test_sidebar_unique_id_and_write_urls(): void
    {
        $job = new PublishSidebarBlogrollJob(42, 7);

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame('42:7', $job->uniqueId());
        $this->assertSame(
            'https://example.com/wp-json/external/v1/blogroll/add',
            PublishSidebarBlogrollJob::blogrollWriteUrl('example.com', null)
        );
        $this->assertSame(
            'https://example.com/wp-json/external/v1/blogroll/update/blog_abc.1',
            PublishSidebarBlogrollJob::blogrollWriteUrl('example.com', 'blog_abc.1')
        );
    }

    public function test_hidden_links_unique_id_and_write_urls(): void
    {
        $job = new PublishHiddenLinksJob(42, 7);

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame('42:7', $job->uniqueId());
        $this->assertSame(
            'https://example.com/wp-json/external/v1/hidden-links/add',
            PublishHiddenLinksJob::hiddenLinksWriteUrl('example.com', null)
        );
        $this->assertSame(
            'https://example.com/wp-json/external/v1/hidden-links/update/hid_abc.1',
            PublishHiddenLinksJob::hiddenLinksWriteUrl('example.com', 'hid_abc.1')
        );
    }

    public function test_sidebar_bulk_retry_failed_only_skips_queued_and_bumps_failed(): void
    {
        Queue::fake();
        $this->createSidebarSchema();

        $campaignId = DB::table('sidebar_campaigns')->insertGetId([
            'admin_id' => 1,
            'status' => 'queued',
            'total_targets' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queuedId = DB::table('sidebar_campaign_tasks')->insertGetId([
            'sidebar_campaign_id' => $campaignId,
            'status' => 'queued',
            'dispatch_generation' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $failedId = DB::table('sidebar_campaign_tasks')->insertGetId([
            'sidebar_campaign_id' => $campaignId,
            'status' => 'failed',
            'attempt_count' => 3,
            'dispatch_generation' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetrySidebarCampaignTasksJob([$campaignId], false))->handle();

        $this->assertSame('queued', SidebarCampaignTask::findOrFail($queuedId)->status);
        $this->assertSame(1, (int) SidebarCampaignTask::findOrFail($queuedId)->dispatch_generation);

        $failed = SidebarCampaignTask::findOrFail($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertSame(0, $failed->attempt_count);
        $this->assertSame(3, (int) $failed->dispatch_generation);
        Queue::assertPushed(PublishSidebarBlogrollJob::class, 1);
        Queue::assertPushed(PublishSidebarBlogrollJob::class, function ($job) use ($failedId) {
            return $job->taskId === $failedId && $job->dispatchGeneration === 3;
        });
    }

    public function test_hidden_links_bulk_retry_failed_only_skips_queued_and_bumps_failed(): void
    {
        Queue::fake();
        $this->createHiddenSchema();

        $campaignId = DB::table('hidden_links_campaigns')->insertGetId([
            'admin_id' => 1,
            'status' => 'queued',
            'total_targets' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queuedId = DB::table('hidden_links_campaigns_tasks')->insertGetId([
            'hidden_links_campaigns_id' => $campaignId,
            'status' => 'queued',
            'dispatch_generation' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $failedId = DB::table('hidden_links_campaigns_tasks')->insertGetId([
            'hidden_links_campaigns_id' => $campaignId,
            'status' => 'failed',
            'attempt_count' => 3,
            'dispatch_generation' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryHiddenLinksCampaignTasksJob([$campaignId], false))->handle();

        $this->assertSame('queued', HiddenLinksCampaignTasks::findOrFail($queuedId)->status);
        $this->assertSame(1, (int) HiddenLinksCampaignTasks::findOrFail($queuedId)->dispatch_generation);

        $failed = HiddenLinksCampaignTasks::findOrFail($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertSame(0, $failed->attempt_count);
        $this->assertSame(3, (int) $failed->dispatch_generation);
        Queue::assertPushed(PublishHiddenLinksJob::class, 1);
        Queue::assertPushed(PublishHiddenLinksJob::class, function ($job) use ($failedId) {
            return $job->taskId === $failedId && $job->dispatchGeneration === 3;
        });
    }

    public function test_sidebar_include_stuck_retries_queued_and_failed(): void
    {
        Queue::fake();
        $this->createSidebarSchema();

        $campaignId = DB::table('sidebar_campaigns')->insertGetId([
            'admin_id' => 1,
            'status' => 'queued',
            'total_targets' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queuedId = DB::table('sidebar_campaign_tasks')->insertGetId([
            'sidebar_campaign_id' => $campaignId,
            'status' => 'queued',
            'dispatch_generation' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $failedId = DB::table('sidebar_campaign_tasks')->insertGetId([
            'sidebar_campaign_id' => $campaignId,
            'status' => 'failed',
            'attempt_count' => 3,
            'dispatch_generation' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetrySidebarCampaignTasksJob([$campaignId], true))->handle();

        $queued = SidebarCampaignTask::findOrFail($queuedId);
        $this->assertSame('queued', $queued->status);
        $this->assertSame(2, (int) $queued->dispatch_generation);

        $failed = SidebarCampaignTask::findOrFail($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertSame(0, $failed->attempt_count);
        $this->assertSame(3, (int) $failed->dispatch_generation);
        Queue::assertPushed(PublishSidebarBlogrollJob::class, 2);
    }

    public function test_hidden_links_include_stuck_retries_queued_and_failed(): void
    {
        Queue::fake();
        $this->createHiddenSchema();

        $campaignId = DB::table('hidden_links_campaigns')->insertGetId([
            'admin_id' => 1,
            'status' => 'queued',
            'total_targets' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queuedId = DB::table('hidden_links_campaigns_tasks')->insertGetId([
            'hidden_links_campaigns_id' => $campaignId,
            'status' => 'queued',
            'dispatch_generation' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $failedId = DB::table('hidden_links_campaigns_tasks')->insertGetId([
            'hidden_links_campaigns_id' => $campaignId,
            'status' => 'failed',
            'attempt_count' => 3,
            'dispatch_generation' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BulkRetryHiddenLinksCampaignTasksJob([$campaignId], true))->handle();

        $queued = HiddenLinksCampaignTasks::findOrFail($queuedId);
        $this->assertSame('queued', $queued->status);
        $this->assertSame(2, (int) $queued->dispatch_generation);

        $failed = HiddenLinksCampaignTasks::findOrFail($failedId);
        $this->assertSame('queued', $failed->status);
        $this->assertSame(0, $failed->attempt_count);
        $this->assertSame(3, (int) $failed->dispatch_generation);
        Queue::assertPushed(PublishHiddenLinksJob::class, 2);
    }

    private function createSidebarSchema(): void
    {
        Schema::dropIfExists('sidebar_campaign_tasks');
        Schema::dropIfExists('sidebar_campaigns');
        Schema::create('sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(1);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamps();
        });
        Schema::create('sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->timestamps();
        });
    }

    private function createHiddenSchema(): void
    {
        Schema::dropIfExists('hidden_links_campaigns_tasks');
        Schema::dropIfExists('hidden_links_campaigns');
        Schema::create('hidden_links_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(1);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamps();
        });
        Schema::create('hidden_links_campaigns_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hidden_links_campaigns_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->timestamps();
        });
    }
}
