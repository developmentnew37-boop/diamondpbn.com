<?php

namespace Tests\Unit;

use App\Data\AgentStatusResult;
use App\Jobs\PublishSidebarBlogrollJob;
use App\Models\Admin;
use App\Models\Admin\SidebarCampaign;
use App\Services\LiveTaskDomainReplacement\LiveTaskBulkDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use App\Services\WordPressAgentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class LiveTaskBulkDomainReplacementServiceTest extends TestCase
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
        Schema::create('sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token')->nullable();
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedInteger('sidebar_count')->default(0);
            $table->string('domain_method')->default('random');
            $table->string('status')->default('failed');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sidebar_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
            $table->unique(['sidebar_campaign_id', 'domain_id']);
        });
        Schema::create('sidebar_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('target_url');
            $table->string('anchor_keyword');
            $table->boolean('nofollow')->default(false);
            $table->timestamps();
        });
        Schema::create('sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedBigInteger('sidebar_campaign_domain_id');
            $table->unsignedBigInteger('sidebar_campaign_link_id');
            $table->string('status')->default('queued');
            $table->json('links_payload')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('remote_response')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamps();
            $table->unique(['sidebar_campaign_id', 'sidebar_campaign_domain_id']);
        });
        Schema::create('sidebar_campaign_domain_replacements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->unsignedBigInteger('sidebar_campaign_id')->nullable();
            $table->unsignedBigInteger('sidebar_campaign_task_id')->nullable();
            $table->unsignedBigInteger('sidebar_campaign_domain_id')->nullable();
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
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sidebar_campaign_domain_replacements');
        Schema::dropIfExists('sidebar_campaign_tasks');
        Schema::dropIfExists('sidebar_campaign_links');
        Schema::dropIfExists('sidebar_campaign_domains');
        Schema::dropIfExists('sidebar_campaigns');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_parse_lines_normalizes_and_skips_blanks(): void
    {
        $lines = $this->bulkService()->parseLines(" HTTPS://Failed.One.com/path \n\nfailed.two.com\n ");

        $this->assertSame(['failed.one.com', 'failed.two.com'], $lines);
    }

    public function test_validate_rejects_too_many_replacements(): void
    {
        $records = $this->sidebarCampaignWithDomains(['failed.one.com'], ['replacement.one.com', 'replacement.two.com']);
        $profile = LiveTaskReplacementProfile::sidebar();

        try {
            $this->bulkService()->validateMappings(
                $profile,
                $records['campaign'],
                $records['admin'],
                ['failed.one.com'],
                ['replacement.one.com', 'replacement.two.com'],
            );
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Too many replacement domains',
                implode(' ', $exception->errors()['replacement_domains'] ?? [])
            );
        }
    }

    public function test_replace_single_domain_directly(): void
    {
        Queue::fake();

        $records = $this->sidebarCampaignWithDomains(
            ['failed.one.com'],
            ['replacement.one.com'],
        );
        $profile = LiveTaskReplacementProfile::sidebar();
        $task = \App\Models\Admin\SidebarCampaignTask::query()->firstOrFail();

        try {
            $replacement = $this->replacementService()->replace(
                $profile,
                $task,
                $records['admin'],
                (int) $records['replacement_ids']['replacement.one.com'],
                (int) DB::table('domains')->where('name', 'failed.one.com')->value('id'),
                (string) Str::uuid(),
                'test',
            );
        } catch (\Throwable $exception) {
            $this->fail($exception->getMessage());
        }

        $this->assertContains($replacement->state, ['completed', 'dispatch_pending']);
    }

    public function test_execute_replaces_two_domain_pairs_and_requeues_tasks(): void
    {
        Queue::fake();

        $records = $this->sidebarCampaignWithDomains(
            ['failed.one.com', 'failed.two.com'],
            ['replacement.one.com', 'replacement.two.com'],
        );
        $profile = LiveTaskReplacementProfile::sidebar();

        $result = $this->bulkService()->execute(
            $profile,
            $records['campaign'],
            $records['admin'],
            ['failed.one.com', 'failed.two.com'],
            ['replacement.one.com', 'replacement.two.com'],
            'Bulk outage recovery',
        );

        $this->assertSame(2, $result->domainsReplaced);
        $this->assertSame(2, $result->postsRequeued);
        $this->assertFalse($result->hasFailures());

        $this->assertSame(
            (int) $records['replacement_ids']['replacement.one.com'],
            (int) DB::table('sidebar_campaign_domains')->where('id', $records['campaign_domain_ids']['failed.one.com'])->value('domain_id')
        );
        $this->assertSame(
            (int) $records['replacement_ids']['replacement.two.com'],
            (int) DB::table('sidebar_campaign_domains')->where('id', $records['campaign_domain_ids']['failed.two.com'])->value('domain_id')
        );

        Queue::assertPushed(PublishSidebarBlogrollJob::class, 2);
    }

    public function test_replaceable_campaign_ids_works_without_locked_until_column(): void
    {
        Schema::create('schedule_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('failed');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
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
            $table->string('status')->default('failed');
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });

        $adminId = $this->admin(Admin::ADMIN, 'owner')->id;
        $failedDomainId = $this->domain($adminId, 1, 'failed.schedule.com');
        $campaignId = (int) DB::table('schedule_campaigns')->insertGetId([
            'campaign_no' => 'SC-1',
            'admin_id' => $adminId,
            'status' => 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $domainRowId = (int) DB::table('schedule_campaigns_domains')->insertGetId([
            'schedule_campaign_id' => $campaignId,
            'domain_id' => $failedDomainId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('schedule_campaigns_posts')->insert([
            'schedule_campaign_id' => $campaignId,
            'schedule_campaign_domain_id' => $domainRowId,
            'status' => 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profile = LiveTaskReplacementProfile::schedulePost();
        $ids = $this->bulkService()->replaceableCampaignIds($profile, [$campaignId]);

        $this->assertSame([$campaignId], $ids);
    }

    private function bulkService(): LiveTaskBulkDomainReplacementService
    {
        return new LiveTaskBulkDomainReplacementService($this->replacementService());
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

    /**
     * @param  array<int, string>  $failedNames
     * @param  array<int, string>  $replacementNames
     * @return array<string, mixed>
     */
    private function sidebarCampaignWithDomains(array $failedNames, array $replacementNames): array
    {
        $admin = $this->admin(Admin::ADMIN, 'owner');
        $campaignId = DB::table('sidebar_campaigns')->insertGetId([
            'campaign_no' => 'SB-BULK',
            'report_token' => Str::random(64),
            'domain_category_id' => 10,
            'admin_id' => $admin->id,
            'status' => 'failed',
            'total_targets' => count($failedNames),
            'failed_targets' => count($failedNames),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignDomainIds = [];

        foreach ($failedNames as $index => $failedName) {
            $failedDomainId = $this->domain($admin->id, 10, $failedName);
            $campaignDomainId = DB::table('sidebar_campaign_domains')->insertGetId([
                'sidebar_campaign_id' => $campaignId,
                'domain_id' => $failedDomainId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $campaignDomainIds[$failedName] = $campaignDomainId;

            $linkId = DB::table('sidebar_campaign_links')->insertGetId([
                'sidebar_campaign_id' => $campaignId,
                'sort_order' => $index + 1,
                'target_url' => 'https://example.com/'.($index + 1),
                'anchor_keyword' => 'Example '.($index + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('sidebar_campaign_tasks')->insert([
                'sidebar_campaign_id' => $campaignId,
                'sidebar_campaign_domain_id' => $campaignDomainId,
                'sidebar_campaign_link_id' => $linkId,
                'status' => 'failed',
                'attempt_count' => 2,
                'last_error' => 'Server down',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $replacementIds = [];
        foreach ($replacementNames as $replacementName) {
            $replacementIds[$replacementName] = $this->domain($admin->id, 10, $replacementName);
        }

        return [
            'admin' => $admin,
            'campaign' => SidebarCampaign::findOrFail($campaignId),
            'campaign_domain_ids' => $campaignDomainIds,
            'replacement_ids' => $replacementIds,
        ];
    }

    private function admin(int $type, string $prefix): Admin
    {
        $id = DB::table('admins')->insertGetId([
            'name' => ucfirst($prefix),
            'slug' => $prefix,
            'email' => $prefix.'@example.test',
            'password' => bcrypt('password'),
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Admin::findOrFail($id);
    }

    private function domain(int $adminId, int $categoryId, string $name): int
    {
        return (int) DB::table('domains')->insertGetId([
            'name' => $name,
            'api_key' => 'secret-key',
            'domain_category_id' => $categoryId,
            'admin_id' => $adminId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
