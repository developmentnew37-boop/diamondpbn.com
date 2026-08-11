<?php

namespace Tests\Unit;

use App\Jobs\BulkRetryCampaignPostsJob;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Admin\CampaignPost;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublishCampaignPostGenerationSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('campaign.jobs.lock_ttl_seconds', 180);
        config()->set('campaign.jobs.max_internal_retries', 5);
        config()->set('campaign.jobs.base_backoff_seconds', 1);

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(1);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key');
            $table->timestamps();
        });
        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });
        Schema::create('campaign_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('article_id')->nullable();
            $table->string('article_title_snapshot');
            $table->text('article_body_snapshot');
            $table->text('keyword');
            $table->text('url');
            $table->string('keyword_type')->default('string');
            $table->string('url_type')->default('string');
            $table->boolean('nofollow')->default(false);
            $table->boolean('sponsored')->default(false);
            $table->boolean('ugc')->default(false);
            $table->boolean('noopener')->default(false);
            $table->boolean('noreferrer')->default(false);
            $table->string('raw_rel_attr')->nullable();
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
            $table->string('lock_token')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('delivery_state')->default(CampaignPost::DELIVERY_NOT_ATTEMPTED);
            $table->string('last_failure_code')->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaign_posts');
        Schema::dropIfExists('campaign_articles');
        Schema::dropIfExists('campaign_domains');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('campaigns');

        parent::tearDown();
    }

    public function test_stale_generation_exits_without_http_or_row_mutation(): void
    {
        $postId = $this->records(4);
        Http::fake();

        (new PublishCampaignPostJob($postId, 3))->handle();

        Http::assertNothingSent();
        $post = CampaignPost::findOrFail($postId);
        $this->assertSame('queued', $post->status);
        $this->assertSame(CampaignPost::DELIVERY_NOT_ATTEMPTED, $post->delivery_state);
        $this->assertNull($post->lock_token);
    }

    public function test_old_serialized_job_defaults_to_generation_zero(): void
    {
        $class = PublishCampaignPostJob::class;
        $payload = sprintf(
            'O:%d:"%s":1:{s:14:"campaignPostId";i:123;}',
            strlen($class),
            $class
        );

        $job = unserialize($payload);

        $this->assertInstanceOf(PublishCampaignPostJob::class, $job);
        $this->assertSame(0, $job->dispatchGeneration);
    }

    public function test_current_generation_publishes_and_marks_remote_created(): void
    {
        $postId = $this->records(7);
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'post_id' => 99,
                'remote_url' => 'https://new.example/post/99',
            ]),
        ]);

        (new PublishCampaignPostJob($postId, 7))->handle();

        Http::assertSentCount(1);
        $post = CampaignPost::findOrFail($postId);
        $this->assertSame('success', $post->status, (string) $post->last_error);
        $this->assertSame(CampaignPost::DELIVERY_REMOTE_CREATED, $post->delivery_state);
        $this->assertSame('99', $post->remote_id);
        $this->assertNull($post->last_failure_code);
    }

    public function test_delivery_failures_are_classified_conservatively(): void
    {
        $this->assertSame(
            ['dns_resolution_failed', CampaignPost::DELIVERY_REMOTE_ABSENT],
            PublishCampaignPostJob::classifyDeliveryFailure(
                new ConnectionException('cURL error 6: Could not resolve host')
            )
        );
        $this->assertSame(
            ['connection_failed', CampaignPost::DELIVERY_REMOTE_ABSENT],
            PublishCampaignPostJob::classifyDeliveryFailure(
                new ConnectionException('cURL error 7: Failed to connect: Connection refused')
            )
        );
        $this->assertSame(
            ['connection_timeout', CampaignPost::DELIVERY_REMOTE_UNKNOWN],
            PublishCampaignPostJob::classifyDeliveryFailure(
                new ConnectionException('cURL error 28: Operation timed out')
            )
        );
        $this->assertSame(
            ['http_server_error', CampaignPost::DELIVERY_REMOTE_UNKNOWN],
            PublishCampaignPostJob::classifyDeliveryFailure(
                new \RuntimeException('WP API failed (503): unavailable')
            )
        );
        $this->assertSame(
            ['malformed_response', CampaignPost::DELIVERY_REMOTE_UNKNOWN],
            PublishCampaignPostJob::classifyDeliveryFailure(
                new \RuntimeException('WP API returned non-JSON response')
            )
        );
    }

    public function test_provable_preconnect_failure_marks_remote_absent_with_stable_code(): void
    {
        config()->set('campaign.jobs.max_internal_retries', 1);
        $postId = $this->records(2);
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

        (new PublishCampaignPostJob($postId, 2))->handle();

        $post = CampaignPost::findOrFail($postId);
        $this->assertSame('failed', $post->status);
        $this->assertSame(CampaignPost::DELIVERY_REMOTE_ABSENT, $post->delivery_state);
        $this->assertSame('dns_resolution_failed', $post->last_failure_code);
    }

    public function test_bulk_retry_skips_failed_posts_with_uncertain_remote_delivery(): void
    {
        Queue::fake();
        $postId = $this->records(5);
        $post = CampaignPost::findOrFail($postId);
        $post->forceFill([
            'status' => 'failed',
            'attempt_count' => 2,
            'delivery_state' => CampaignPost::DELIVERY_REMOTE_UNKNOWN,
        ])->save();

        (new BulkRetryCampaignPostsJob([$post->campaign_id]))->handle();

        $post->refresh();
        $this->assertSame('failed', $post->status);
        $this->assertSame(2, $post->attempt_count);
        Queue::assertNothingPushed();
    }

    private function records(int $generation): int
    {
        $campaignId = DB::table('campaigns')->insertGetId([
            'admin_id' => 1,
            'status' => 'queued',
            'total_targets' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $domainId = DB::table('domains')->insertGetId([
            'name' => 'new.example',
            'api_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaignDomainId = DB::table('campaign_domains')->insertGetId([
            'campaign_id' => $campaignId,
            'domain_id' => $domainId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaignArticleId = DB::table('campaign_articles')->insertGetId([
            'campaign_id' => $campaignId,
            'article_title_snapshot' => 'Test title',
            'article_body_snapshot' => '<p>This is enough test content for a campaign post.</p>',
            'keyword' => 'keyword',
            'url' => 'https://target.example',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('campaign_posts')->insertGetId([
            'campaign_id' => $campaignId,
            'campaign_domain_id' => $campaignDomainId,
            'campaign_article_id' => $campaignArticleId,
            'dispatch_generation' => $generation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
