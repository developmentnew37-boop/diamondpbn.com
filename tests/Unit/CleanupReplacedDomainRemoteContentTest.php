<?php

namespace Tests\Unit;

use App\Data\AgentStatusResult;
use App\Jobs\CleanupReplacedDomainRemoteContentJob;
use App\Jobs\RepublishConvertedSchedulePostAfterDomainReplaceJob;
use App\Models\Admin;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignDomainReplacement;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\LiveTaskDomainReplacement\LiveTaskDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use App\Services\WordPressAgentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CleanupReplacedDomainRemoteContentTest extends TestCase
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
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('schedule_campaign_domain_replacements');
        Schema::dropIfExists('schedule_campaigns_posts');
        Schema::dropIfExists('schedule_campaigns_domains');
        Schema::dropIfExists('schedule_campaigns');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('admins');

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

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key')->nullable();
            $table->string('api_key_lookup_hash')->nullable();
            $table->unsignedBigInteger('domain_category_id');
            $table->unsignedBigInteger('admin_id');
            $table->integer('status')->default(1);
            $table->string('agent_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_status_code')->nullable();
            $table->string('last_status_probe')->nullable();
            $table->text('last_status_message')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token')->nullable();
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('failed');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('converted_from_campaign_id')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('schedule_campaign_domain_id');
            $table->unsignedBigInteger('schedule_campaign_article_id')->nullable();
            $table->unsignedBigInteger('source_campaign_post_id')->nullable();
            $table->boolean('is_converted_live')->default(false);
            $table->string('conversion_phase')->nullable();
            $table->text('last_conversion_error')->nullable();
            $table->timestamp('last_conversion_attempt_at')->nullable();
            $table->timestamp('schedule_at')->nullable();
            $table->string('status')->default('failed');
            $table->string('remote_id')->nullable();
            $table->string('remote_title')->nullable();
            $table->string('remote_url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('remote_status')->nullable();
            $table->json('remote_response')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->timestamps();
        });

        Schema::create('schedule_campaign_domain_replacements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->unsignedBigInteger('schedule_campaign_id')->nullable();
            $table->unsignedBigInteger('schedule_campaign_post_id')->nullable();
            $table->unsignedBigInteger('schedule_campaign_domain_id')->nullable();
            $table->unsignedBigInteger('old_domain_id')->nullable();
            $table->unsignedBigInteger('new_domain_id')->nullable();
            $table->string('old_hostname');
            $table->string('new_hostname');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('previous_status');
            $table->string('result_status')->nullable();
            $table->json('health_snapshot')->nullable();
            $table->unsignedInteger('dispatch_generation');
            $table->string('state')->default('pending');
            $table->text('error')->nullable();
            $table->string('previous_remote_id', 191)->nullable();
            $table->text('previous_remote_url')->nullable();
            $table->string('old_remote_cleanup_status', 32)->nullable();
            $table->text('old_remote_cleanup_error')->nullable();
            $table->timestamp('old_remote_cleaned_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_converted_replace_captures_previous_remote_and_queues_cleanup(): void
    {
        Queue::fake();

        $fixture = $this->convertedScheduleFixture(remoteId: '12345', remoteUrl: 'https://old.example/post/12345');
        $profile = LiveTaskReplacementProfile::convertedSchedulePost();
        $task = ScheduleCampaignPost::query()->findOrFail($fixture['task_id']);

        $replacement = $this->replacementService()->replace(
            $profile,
            $task,
            $fixture['admin'],
            $fixture['new_domain_id'],
            $fixture['old_domain_id'],
            (string) Str::uuid(),
            'cleanup test',
        );

        $this->assertSame('12345', $replacement->previous_remote_id);
        $this->assertSame('https://old.example/post/12345', $replacement->previous_remote_url);
        $this->assertSame('pending', $replacement->old_remote_cleanup_status);

        Queue::assertPushed(RepublishConvertedSchedulePostAfterDomainReplaceJob::class);
        Queue::assertPushed(CleanupReplacedDomainRemoteContentJob::class, function (CleanupReplacedDomainRemoteContentJob $job) use ($replacement) {
            return $job->profileLabel === 'converted_schedule_post'
                && $job->replacementId === (int) $replacement->id
                && $job->queue === CleanupReplacedDomainRemoteContentJob::QUEUE;
        });

        $task->refresh();
        $this->assertNull($task->remote_id);
        $this->assertSame('pending_draft', $task->conversion_phase);
    }

    public function test_cleanup_job_marks_cleaned_on_successful_delete(): void
    {
        Http::fake([
            '*/wp-json/external/v1/posts/delete/*' => Http::response(['success' => true], 200),
        ]);

        $fixture = $this->convertedScheduleFixture(remoteId: '99', remoteUrl: null);
        $replacementId = $this->seedPendingCleanupReplacement($fixture, '99');

        (new CleanupReplacedDomainRemoteContentJob('converted_schedule_post', $replacementId))->handle();

        $replacement = ScheduleCampaignDomainReplacement::query()->findOrFail($replacementId);
        $this->assertSame('cleaned', $replacement->old_remote_cleanup_status);
        $this->assertNotNull($replacement->old_remote_cleaned_at);
        $this->assertNull($replacement->old_remote_cleanup_error);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/wp-json/external/v1/posts/delete/99')
                && $request->method() === 'DELETE';
        });
    }

    public function test_cleanup_job_marks_failed_when_delete_fails_on_last_attempt(): void
    {
        Http::fake([
            '*/wp-json/external/v1/posts/delete/*' => Http::response(['success' => false], 500),
        ]);

        $fixture = $this->convertedScheduleFixture(remoteId: '77', remoteUrl: null);
        $replacementId = $this->seedPendingCleanupReplacement($fixture, '77');

        $job = new class('converted_schedule_post', $replacementId) extends CleanupReplacedDomainRemoteContentJob
        {
            public function attempts(): int
            {
                return 5;
            }
        };
        $job->tries = 5;
        $job->handle();

        $replacement = ScheduleCampaignDomainReplacement::query()->findOrFail($replacementId);
        $this->assertSame('failed', $replacement->old_remote_cleanup_status);
        $this->assertNotNull($replacement->old_remote_cleanup_error);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function seedPendingCleanupReplacement(array $fixture, string $previousRemoteId): int
    {
        return (int) DB::table('schedule_campaign_domain_replacements')->insertGetId([
            'request_uuid' => (string) Str::uuid(),
            'schedule_campaign_id' => $fixture['campaign_id'],
            'schedule_campaign_post_id' => $fixture['task_id'],
            'schedule_campaign_domain_id' => $fixture['domain_row_id'],
            'old_domain_id' => $fixture['old_domain_id'],
            'new_domain_id' => $fixture['new_domain_id'],
            'old_hostname' => 'old.example',
            'new_hostname' => 'new.example',
            'admin_id' => $fixture['admin']->id,
            'previous_status' => 'failed',
            'result_status' => 'queued',
            'dispatch_generation' => 1,
            'state' => 'completed',
            'previous_remote_id' => $previousRemoteId,
            'old_remote_cleanup_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function convertedScheduleFixture(?string $remoteId, ?string $remoteUrl): array
    {
        $adminId = DB::table('admins')->insertGetId([
            'name' => 'Owner',
            'slug' => 'owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('password'),
            'type' => Admin::ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = Admin::findOrFail($adminId);

        $oldDomainId = (int) DB::table('domains')->insertGetId([
            'name' => 'old.example',
            'api_key' => 'old-key',
            'domain_category_id' => 10,
            'admin_id' => $adminId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newDomainId = (int) DB::table('domains')->insertGetId([
            'name' => 'new.example',
            'api_key' => 'new-key',
            'domain_category_id' => 10,
            'admin_id' => $adminId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignId = DB::table('schedule_campaigns')->insertGetId([
            'campaign_no' => 'SC-CONV',
            'report_token' => Str::random(64),
            'domain_category_id' => 10,
            'admin_id' => $adminId,
            'status' => 'failed',
            'total_targets' => 1,
            'failed_targets' => 1,
            'converted_from_campaign_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $domainRowId = DB::table('schedule_campaigns_domains')->insertGetId([
            'schedule_campaign_id' => $campaignId,
            'domain_id' => $oldDomainId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $taskId = DB::table('schedule_campaigns_posts')->insertGetId([
            'schedule_campaign_id' => $campaignId,
            'schedule_campaign_domain_id' => $domainRowId,
            'source_campaign_post_id' => 55,
            'is_converted_live' => 1,
            'conversion_phase' => 'failed',
            'schedule_at' => now()->addDays(7),
            'status' => 'failed',
            'remote_id' => $remoteId,
            'remote_url' => $remoteUrl,
            'remote_status' => 'draft',
            'attempt_count' => 2,
            'last_error' => 'Domain down',
            'dispatch_generation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'admin' => $admin,
            'campaign_id' => $campaignId,
            'campaign' => ScheduleCampaign::findOrFail($campaignId),
            'task_id' => $taskId,
            'domain_row_id' => $domainRowId,
            'old_domain_id' => $oldDomainId,
            'new_domain_id' => $newDomainId,
        ];
    }

    private function replacementService(): LiveTaskDomainReplacementService
    {
        $status = Mockery::mock(WordPressAgentStatusService::class);
        $status->shouldReceive('probe')->andReturn(new AgentStatusResult(
            true,
            'online',
            'Connected',
            true,
            '8.1.5',
            'rest',
            200,
            12
        ));

        return new LiveTaskDomainReplacementService($status);
    }
}
