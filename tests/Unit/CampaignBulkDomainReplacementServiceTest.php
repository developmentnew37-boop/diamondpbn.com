<?php

namespace Tests\Unit;

use App\Data\AgentStatusResult;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use App\Services\CampaignBulkDomainReplacementService;
use App\Services\CampaignDomainReplacementService;
use App\Services\WordPressAgentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CampaignBulkDomainReplacementServiceTest extends TestCase
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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token');
            $table->unsignedBigInteger('domain_category_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'domain_id']);
        });
        Schema::create('campaign_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->timestamps();
        });
        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_domain_id');
            $table->unsignedBigInteger('campaign_article_id');
            $table->string('status')->default('queued');
            $table->boolean('is_sticky')->default(false);
            $table->string('remote_id')->nullable();
            $table->string('remote_title')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('content_updated_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('lock_token')->nullable();
            $table->string('delivery_state')->default(CampaignPost::DELIVERY_NOT_ATTEMPTED);
            $table->string('last_failure_code')->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->boolean('auto_replace_domains')->default(false);
            $table->timestamps();
        });
        Schema::create('campaign_domain_replacements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('campaign_post_id')->nullable();
            $table->unsignedBigInteger('campaign_domain_id')->nullable();
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
        Schema::dropIfExists('campaign_domain_replacements');
        Schema::dropIfExists('campaign_posts');
        Schema::dropIfExists('campaign_articles');
        Schema::dropIfExists('campaign_domains');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_parse_lines_normalizes_and_skips_blanks(): void
    {
        $service = $this->bulkService();

        $lines = $service->parseLines(" HTTPS://Failed.One.com/path \n\nfailed.two.com\n ");

        $this->assertSame(['failed.one.com', 'failed.two.com'], $lines);
    }

    public function test_validate_rejects_too_many_replacements(): void
    {
        $records = $this->campaignWithDomains(['failed.one.com'], ['replacement.one.com', 'replacement.two.com']);
        $service = $this->bulkService();

        try {
            $service->validateMappings(
                $records['campaign'],
                $records['admin'],
                ['failed.one.com'],
                ['replacement.one.com', 'replacement.two.com'],
            );
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Too many replacement domains', implode(' ', $exception->errors()['replacement_domains'] ?? []));
        }
    }

    public function test_validate_rejects_duplicate_failed_domains(): void
    {
        $records = $this->campaignWithDomains(['failed.one.com'], ['replacement.one.com']);
        $service = $this->bulkService();

        $this->expectException(ValidationException::class);
        $service->validateMappings(
            $records['campaign'],
            $records['admin'],
            ['failed.one.com', 'failed.one.com'],
            ['replacement.one.com'],
        );
    }

    public function test_validate_rejects_unknown_domains(): void
    {
        $records = $this->campaignWithDomains(['failed.one.com'], ['replacement.one.com']);
        $service = $this->bulkService();

        $this->expectException(ValidationException::class);
        $service->validateMappings(
            $records['campaign'],
            $records['admin'],
            ['missing.example'],
            ['replacement.one.com'],
        );
    }

    public function test_validate_rejects_replacement_already_on_campaign(): void
    {
        $records = $this->campaignWithDomains(['failed.one.com', 'failed.two.com'], ['replacement.one.com', 'failed.two.com']);
        $service = $this->bulkService();

        $this->expectException(ValidationException::class);
        $service->validateMappings(
            $records['campaign'],
            $records['admin'],
            ['failed.one.com', 'failed.two.com'],
            ['replacement.one.com', 'failed.two.com'],
        );
    }

    public function test_execute_replaces_two_domain_pairs_and_requeues_sibling_posts(): void
    {
        Queue::fake();

        $records = $this->campaignWithDomains(
            ['failed.one.com', 'failed.two.com'],
            ['replacement.one.com', 'replacement.two.com'],
            postsPerDomain: 2,
        );

        $service = $this->bulkService();
        $result = $service->execute(
            $records['campaign'],
            $records['admin'],
            ['failed.one.com', 'failed.two.com'],
            ['replacement.one.com', 'replacement.two.com'],
            'Bulk outage recovery',
        );

        $this->assertSame(2, $result->domainsReplaced);
        $this->assertSame(4, $result->postsRequeued);
        $this->assertFalse($result->hasFailures());

        $this->assertSame(
            (int) $records['replacement_ids']['replacement.one.com'],
            (int) DB::table('campaign_domains')->where('id', $records['campaign_domain_ids']['failed.one.com'])->value('domain_id')
        );
        $this->assertSame(
            (int) $records['replacement_ids']['replacement.two.com'],
            (int) DB::table('campaign_domains')->where('id', $records['campaign_domain_ids']['failed.two.com'])->value('domain_id')
        );

        Queue::assertPushed(PublishCampaignPostJob::class, 4);
    }

    private function bulkService(): CampaignBulkDomainReplacementService
    {
        return new CampaignBulkDomainReplacementService($this->replacementService());
    }

    private function replacementService(): CampaignDomainReplacementService
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

        return new CampaignDomainReplacementService($status);
    }

    /**
     * @param  array<int, string>  $failedNames
     * @param  array<int, string>  $replacementNames
     * @return array<string, mixed>
     */
    private function campaignWithDomains(
        array $failedNames,
        array $replacementNames,
        int $postsPerDomain = 1,
    ): array {
        $admin = $this->admin(Admin::ADMIN, 'owner');
        $campaignId = DB::table('campaigns')->insertGetId([
            'campaign_no' => 'CMP-BULK',
            'report_token' => Str::random(64),
            'domain_category_id' => 10,
            'admin_id' => $admin->id,
            'status' => 'failed',
            'total_targets' => count($failedNames) * $postsPerDomain,
            'failed_targets' => count($failedNames) * $postsPerDomain,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignDomainIds = [];
        $articleIds = [];

        foreach ($failedNames as $index => $failedName) {
            $failedDomainId = $this->domain($admin->id, 10, $failedName);
            $campaignDomainId = DB::table('campaign_domains')->insertGetId([
                'campaign_id' => $campaignId,
                'domain_id' => $failedDomainId,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $campaignDomainIds[$failedName] = $campaignDomainId;

            for ($postIndex = 0; $postIndex < $postsPerDomain; $postIndex++) {
                $articleId = DB::table('campaign_articles')->insertGetId([
                    'campaign_id' => $campaignId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $articleIds[] = $articleId;

                DB::table('campaign_posts')->insert([
                    'campaign_id' => $campaignId,
                    'campaign_domain_id' => $campaignDomainId,
                    'campaign_article_id' => $articleId,
                    'status' => 'failed',
                    'attempt_count' => 2,
                    'last_error' => 'Server down',
                    'delivery_state' => CampaignPost::DELIVERY_REMOTE_ABSENT,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $replacementIds = [];
        foreach ($replacementNames as $replacementName) {
            $replacementIds[$replacementName] = $this->domain($admin->id, 10, $replacementName);
        }

        return [
            'admin' => $admin,
            'campaign' => Campaign::findOrFail($campaignId),
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

    private function domain(
        int $adminId,
        int $categoryId,
        string $name,
        int $status = 1,
        string $apiKey = 'secret-key',
    ): int {
        return (int) DB::table('domains')->insertGetId([
            'name' => $name,
            'api_key' => $apiKey,
            'domain_category_id' => $categoryId,
            'admin_id' => $adminId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
