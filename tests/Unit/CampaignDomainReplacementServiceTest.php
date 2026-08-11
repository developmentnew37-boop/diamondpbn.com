<?php

namespace Tests\Unit;

use App\Data\AgentStatusResult;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignDomainReplacement;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\Domain;
use App\Services\CampaignDomainReplacementService;
use App\Services\WordPressAgentStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CampaignDomainReplacementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_candidates_enforce_owner_active_duplicate_and_api_key_rules(): void
    {
        $records = $this->records();
        $otherAdmin = $this->admin(Admin::ADMIN, 'other');
        $eligible = $this->domain($records['admin']->id, 10, 'eligible.example');
        $crossCategory = $this->domain($records['admin']->id, 11, 'wrong-category.example');
        $this->domain($records['admin']->id, 10, 'inactive.example', status: 0);
        $this->domain($records['admin']->id, 10, 'missing-key.example', apiKey: '');
        $foreign = $this->domain($otherAdmin->id, 10, 'foreign.example');

        $candidateIds = $this->service()->candidates(
            $records['campaign'],
            $records['post'],
            $records['admin']
        )->pluck('id')->all();

        $this->assertSame([$eligible, $crossCategory], $candidateIds);

        $super = $this->admin(Admin::SUPER_ADMIN, 'super');
        $superIds = $this->service()->candidates(
            $records['campaign'],
            $records['post'],
            $super
        )->pluck('id')->all();
        $this->assertContains($foreign, $superIds);
    }

    public function test_lookup_and_manual_domain_resolution_explain_ineligibility(): void
    {
        $records = $this->records();
        $service = $this->service();

        $disconnected = $this->domain($records['admin']->id, 10, 'shopatstar.co.uk', status: 0);

        $this->assertSame(
            $disconnected,
            $service->lookupDomain('https://shopatstar.co.uk/path')->id
        );

        $this->assertNull($service->ineligibilityReason(
            $records['campaign'],
            $records['post'],
            $records['admin'],
            Domain::find($disconnected),
            manual: true
        ));

        $this->assertSame(
            'This domain is not connected. Reconnect it from the Domains page first.',
            $service->ineligibilityReason(
                $records['campaign'],
                $records['post'],
                $records['admin'],
                Domain::find($disconnected)
            )
        );

        $this->assertSame(
            $disconnected,
            $service->resolveReplacementDomainId(
                $records['campaign'],
                $records['post'],
                $records['admin'],
                null,
                'shopatstar.co.uk'
            )
        );

        $eligible = $this->domain($records['admin']->id, 10, 'manual.example');
        $this->assertSame(
            $eligible,
            $service->resolveReplacementDomainId(
                $records['campaign'],
                $records['post'],
                $records['admin'],
                null,
                'manual.example'
            )
        );

        $crossCategory = $this->domain($records['admin']->id, 99, 'other-category.example');
        $this->assertNull($service->ineligibilityReason(
            $records['campaign'],
            $records['post'],
            $records['admin'],
            Domain::find($crossCategory)
        ));
    }

    public function test_cross_category_replacement_works_without_reason(): void
    {
        Queue::fake();
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 99, 'cross-category.example');

        $replacement = $this->service()->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            ''
        );

        $this->assertSame('completed', $replacement->state);
        $this->assertNull($replacement->reason);
        $this->assertSame($candidate, (int) $records['post']->fresh()->campaignDomain->domain_id);
        $this->assertFalse($records['post']->fresh()->auto_replace_domains);
    }

    public function test_automatic_replacement_is_not_enabled_after_manual_replace(): void
    {
        Queue::fake();
        $records = $this->records();
        $firstReplacement = $this->domain($records['admin']->id, 10, 'first-retry.example');
        $secondReplacement = $this->domain($records['admin']->id, 10, 'second-retry.example');
        $service = $this->service();

        $service->replace(
            $records['post'],
            $records['admin'],
            $firstReplacement,
            $records['old_domain_id'],
            (string) Str::uuid(),
            'Manual replacement',
            manual: true,
        );

        $post = $records['post']->fresh();
        $post->forceFill([
            'status' => 'failed',
            'delivery_state' => CampaignPost::DELIVERY_REMOTE_UNKNOWN,
            'attempt_count' => 5,
            'last_error' => 'WP API failed (403)',
        ])->save();

        $this->assertFalse($service->attemptAutomaticReplacement($post->fresh()));

        $post = $post->fresh();
        $this->assertSame('failed', $post->status);
        $this->assertSame($firstReplacement, (int) $post->campaignDomain->domain_id);
        $this->assertNotSame($secondReplacement, (int) $post->campaignDomain->domain_id);
        Queue::assertPushed(PublishCampaignPostJob::class, 1);
    }

    public function test_owner_super_and_member_authorization(): void
    {
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');
        $other = $this->admin(Admin::ADMIN, 'other');
        $member = $this->admin(Admin::MEMBER, 'member');

        foreach ([$other, $member] as $admin) {
            try {
                $this->service()->replace(
                    $records['post'],
                    $admin,
                    $candidate,
                    $records['old_domain_id'],
                    (string) Str::uuid(),
                    'Not allowed'
                );
                $this->fail('Authorization exception was not thrown.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $super = $this->admin(Admin::SUPER_ADMIN, 'super');
        $crossOwnerCandidate = $this->domain($other->id, 10, 'cross-owner.example');
        Queue::fake();
        $replacement = $this->service()->replace(
            $records['post']->fresh(),
            $super,
            $crossOwnerCandidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            'Super admin replacement'
        );
        $this->assertSame('completed', $replacement->state);
    }

    public function test_atomic_replacement_preserves_identity_and_resets_post_then_dispatches_generation(): void
    {
        Queue::fake();
        $records = $this->records(sticky: true, postStatus: 'failed', generation: 4);
        $candidate = $this->domain($records['admin']->id, 10, 'healthy.example');
        $secondDomain = $this->domain($records['admin']->id, 10, 'completed.example');
        $secondCampaignDomain = DB::table('campaign_domains')->insertGetId([
            'campaign_id' => $records['campaign']->id,
            'domain_id' => $secondDomain,
            'sort_order' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('campaign_posts')->insert([
            'campaign_id' => $records['campaign']->id,
            'campaign_domain_id' => $secondCampaignDomain,
            'campaign_article_id' => $records['article_id'],
            'status' => 'success',
            'delivery_state' => CampaignPost::DELIVERY_REMOTE_CREATED,
            'remote_id' => '22',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportToken = $records['campaign']->report_token;
        $replacement = $this->service()->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            'Old domain is permanently unavailable'
        );

        $post = $records['post']->fresh();
        $campaign = $records['campaign']->fresh();
        $campaignDomain = DB::table('campaign_domains')->find($records['campaign_domain_id']);

        $this->assertSame($candidate, (int) $campaignDomain->domain_id);
        $this->assertSame(7, (int) $campaignDomain->sort_order);
        $this->assertSame($records['campaign_domain_id'], $post->campaign_domain_id);
        $this->assertSame($records['article_id'], $post->campaign_article_id);
        $this->assertTrue($post->is_sticky);
        $this->assertSame('queued', $post->status);
        $this->assertSame(0, $post->attempt_count);
        $this->assertSame(5, $post->dispatch_generation);
        $this->assertSame(CampaignPost::DELIVERY_NOT_ATTEMPTED, $post->delivery_state);
        $this->assertNull($post->last_error);
        $this->assertNull($post->remote_id);
        $this->assertSame(2, $campaign->total_targets);
        $this->assertSame(1, $campaign->completed_targets);
        $this->assertSame(0, $campaign->failed_targets);
        $this->assertSame('running', $campaign->status);
        $this->assertNull($campaign->finished_at);
        $this->assertSame($reportToken, $campaign->report_token);
        $this->assertSame('completed', $replacement->state);
        $this->assertSame('old.example', $replacement->old_hostname);
        $this->assertSame('healthy.example', $replacement->new_hostname);
        Queue::assertPushed(
            PublishCampaignPostJob::class,
            fn (PublishCampaignPostJob $job) => $job->campaignPostId === $post->id
                && $job->dispatchGeneration === 5
        );
    }

    public function test_manual_replacement_proceeds_despite_failed_probe(): void
    {
        Queue::fake();
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'offline.example', status: 0);
        $service = $this->service(new AgentStatusResult(false, 'domain_offline', 'Connection failed'));

        $replacement = $service->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            '',
            manual: true
        );

        $this->assertSame('completed', $replacement->state);
        $this->assertSame($candidate, (int) DB::table('campaign_domains')->find($records['campaign_domain_id'])->domain_id);
        $this->assertFalse($replacement->health_snapshot['ok']);
        Queue::assertPushed(PublishCampaignPostJob::class, 1);
    }

    public function test_dropdown_replacement_still_blocks_failed_probe(): void
    {
        Queue::fake();
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'offline.example');
        $service = $this->service(new AgentStatusResult(false, 'domain_offline', 'Connection failed'));

        try {
            $service->replace(
                $records['post'],
                $records['admin'],
                $candidate,
                $records['old_domain_id'],
                (string) Str::uuid(),
                'Offline'
            );
            $this->fail('Validation exception was not thrown.');
        } catch (ValidationException) {
            $this->assertSame(0, (int) DB::table('domains')->find($candidate)->status);
        }

        $this->assertSame(
            $records['old_domain_id'],
            (int) DB::table('campaign_domains')->find($records['campaign_domain_id'])->domain_id
        );
        $this->assertDatabaseCount('campaign_domain_replacements', 0);
        Queue::assertNothingPushed();
    }

    public function test_unsafe_delivery_states_and_remote_metadata_are_rejected(): void
    {
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');

        foreach ([
            ['delivery_state' => CampaignPost::DELIVERY_REMOTE_CREATED],
            ['remote_id' => '123'],
            ['status' => 'publishing'],
            ['locked_at' => now()],
        ] as $unsafe) {
            $records['post']->forceFill([
                'status' => 'failed',
                'delivery_state' => CampaignPost::DELIVERY_REMOTE_ABSENT,
                'remote_id' => null,
                'locked_at' => null,
                'lock_token' => null,
                ...$unsafe,
            ])->save();

            $this->expectValidation(fn () => $this->service()->replace(
                $records['post']->fresh(),
                $records['admin'],
                $candidate,
                $records['old_domain_id'],
                (string) Str::uuid(),
                'Unsafe'
            ));
        }
    }

    public function test_remote_unknown_failed_post_can_be_replaced(): void
    {
        Queue::fake();
        $records = $this->records(postStatus: 'failed');
        $candidate = $this->domain($records['admin']->id, 10, 'replacement.example');

        $records['post']->forceFill([
            'delivery_state' => CampaignPost::DELIVERY_REMOTE_UNKNOWN,
            'last_error' => 'WP API failed (403)',
        ])->save();

        $this->assertNull(CampaignDomainReplacementService::ineligibleReason($records['post']->fresh()));

        $replacement = $this->service()->replace(
            $records['post']->fresh(),
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            '',
            manual: true,
        );

        $this->assertSame('completed', $replacement->state);
    }

    public function test_idempotent_repeat_does_not_probe_or_dispatch_twice_and_uuid_mismatch_rejects(): void
    {
        Queue::fake();
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');
        $uuid = (string) Str::uuid();
        $status = Mockery::mock(WordPressAgentStatusService::class);
        $status->shouldReceive('probe')->once()->andReturn($this->online());
        $service = new CampaignDomainReplacementService($status);

        $first = $service->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            $uuid,
            'Repeat-safe'
        );
        $second = $service->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            $uuid,
            'Repeat-safe'
        );

        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(PublishCampaignPostJob::class, 1);

        $different = $this->domain($records['admin']->id, 10, 'different.example');
        $this->expectValidation(fn () => $service->replace(
            $records['post'],
            $records['admin'],
            $different,
            $records['old_domain_id'],
            $uuid,
            'Mismatched UUID'
        ));
    }

    public function test_dispatch_failure_reports_committed_replacement_and_same_uuid_retries_safely(): void
    {
        $records = $this->records(postStatus: 'failed', generation: 2);
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');
        $uuid = (string) Str::uuid();

        Bus::shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Queue unavailable'));

        $replacement = $this->service()->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            $uuid,
            'Dispatch failure'
        );

        $this->assertSame('dispatch_failed', $replacement->state);
        $this->assertSame(
            $candidate,
            (int) DB::table('campaign_domains')->find($records['campaign_domain_id'])->domain_id
        );
        $this->assertSame(3, (int) DB::table('campaign_posts')->find($records['post']->id)->dispatch_generation);
        $this->assertSame('queued', DB::table('campaign_posts')->find($records['post']->id)->status);

        Bus::fake();

        $retried = $this->service()->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            $uuid,
            'Dispatch failure'
        );

        $this->assertSame($replacement->id, $retried->id);
        $this->assertSame('completed', $retried->state);
        Bus::assertDispatched(
            PublishCampaignPostJob::class,
            fn (PublishCampaignPostJob $job) => $job->campaignPostId === $records['post']->id
                && $job->dispatchGeneration === 3
        );
    }

    public function test_race_rolls_back_changes_and_preserves_sanitized_failed_audit(): void
    {
        Queue::fake();
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');
        $status = Mockery::mock(WordPressAgentStatusService::class);
        $status->shouldReceive('probe')->once()->andReturnUsing(function () use ($records) {
            DB::table('campaign_posts')->where('id', $records['post']->id)->update([
                'delivery_state' => CampaignPost::DELIVERY_REMOTE_CREATED,
                'remote_id' => '999',
            ]);

            return $this->online();
        });

        $this->expectValidation(fn () => (new CampaignDomainReplacementService($status))->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            'Race test'
        ));

        $audit = CampaignDomainReplacement::firstOrFail();
        $this->assertSame('failed', $audit->state);
        $this->assertSame('Replacement could not be completed.', $audit->error);
        $this->assertStringNotContainsString('remote_unknown', (string) $audit->error);
        $this->assertSame(
            $records['old_domain_id'],
            (int) DB::table('campaign_domains')->find($records['campaign_domain_id'])->domain_id
        );
        Queue::assertNothingPushed();
    }

    public function test_completed_audit_is_immutable(): void
    {
        Queue::fake();
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');
        $audit = $this->service()->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            'Immutable'
        );

        $this->expectException(\LogicException::class);
        $audit->update(['reason' => 'Changed']);
    }

    public function test_committed_dispatch_failure_audit_details_cannot_be_changed_or_deleted(): void
    {
        $records = $this->records();
        $candidate = $this->domain($records['admin']->id, 10, 'candidate.example');

        Bus::shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Queue unavailable'));

        $audit = $this->service()->replace(
            $records['post'],
            $records['admin'],
            $candidate,
            $records['old_domain_id'],
            (string) Str::uuid(),
            'Immutable after commit'
        );

        try {
            $audit->update(['reason' => 'Changed']);
            $this->fail('Committed audit details were mutable.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(\LogicException::class);
        $audit->delete();
    }

    private function service(?AgentStatusResult $result = null): CampaignDomainReplacementService
    {
        $status = Mockery::mock(WordPressAgentStatusService::class);
        $status->shouldReceive('probe')->andReturn($result ?? $this->online());

        return new CampaignDomainReplacementService($status);
    }

    private function online(): AgentStatusResult
    {
        return new AgentStatusResult(
            true,
            'online',
            'Connected',
            true,
            '8.1.5',
            'rest',
            200,
            12
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function records(bool $sticky = false, string $postStatus = 'queued', int $generation = 0): array
    {
        $admin = $this->admin(Admin::ADMIN, 'owner');
        $oldDomainId = $this->domain($admin->id, 10, 'old.example');
        $campaignId = DB::table('campaigns')->insertGetId([
            'campaign_no' => 'CMP-1',
            'report_token' => Str::random(64),
            'domain_category_id' => 10,
            'admin_id' => $admin->id,
            'status' => $postStatus === 'failed' ? 'failed' : 'queued',
            'is_sticky_campaign' => $sticky,
            'total_targets' => 99,
            'failed_targets' => $postStatus === 'failed' ? 1 : 0,
            'finished_at' => $postStatus === 'failed' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaignDomainId = DB::table('campaign_domains')->insertGetId([
            'campaign_id' => $campaignId,
            'domain_id' => $oldDomainId,
            'sort_order' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $articleId = DB::table('campaign_articles')->insertGetId([
            'campaign_id' => $campaignId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $postId = DB::table('campaign_posts')->insertGetId([
            'campaign_id' => $campaignId,
            'campaign_domain_id' => $campaignDomainId,
            'campaign_article_id' => $articleId,
            'status' => $postStatus,
            'is_sticky' => $sticky,
            'attempt_count' => $postStatus === 'failed' ? 3 : 0,
            'last_error' => $postStatus === 'failed' ? 'DNS failed' : null,
            'delivery_state' => $postStatus === 'failed'
                ? CampaignPost::DELIVERY_REMOTE_ABSENT
                : CampaignPost::DELIVERY_NOT_ATTEMPTED,
            'dispatch_generation' => $generation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'admin' => $admin,
            'campaign' => Campaign::findOrFail($campaignId),
            'post' => CampaignPost::findOrFail($postId),
            'old_domain_id' => $oldDomainId,
            'campaign_domain_id' => $campaignDomainId,
            'article_id' => $articleId,
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
        return DB::table('domains')->insertGetId([
            'name' => $name,
            'api_key' => $apiKey,
            'domain_category_id' => $categoryId,
            'admin_id' => $adminId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Validation exception was not thrown.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
