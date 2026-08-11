<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignDomainReplacementMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('admins', fn (Blueprint $table) => $table->id());
        Schema::create('domains', fn (Blueprint $table) => $table->id());
        Schema::create('campaigns', fn (Blueprint $table) => $table->id());
        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
        });
        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_domain_id');
            $table->string('status')->default('queued');
            $table->string('remote_id')->nullable();
            $table->string('remote_title')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaign_domain_replacements');
        Schema::dropIfExists('campaign_posts');
        Schema::dropIfExists('campaign_domains');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_migration_backfills_delivery_state_without_assuming_ambiguous_attempts_are_absent(): void
    {
        $campaignId = DB::table('campaigns')->insertGetId([]);
        $campaignDomainId = DB::table('campaign_domains')->insertGetId(['campaign_id' => $campaignId]);

        $notAttempted = $this->insertPost($campaignId, $campaignDomainId, 'queued', 0);
        $attempted = $this->insertPost($campaignId, $campaignDomainId, 'failed', 2);
        $publishing = $this->insertPost($campaignId, $campaignDomainId, 'publishing', 0);
        $created = $this->insertPost($campaignId, $campaignDomainId, 'failed', 1, '123');
        $queuedWithRetryEvidence = $this->insertPost(
            $campaignId,
            $campaignDomainId,
            'queued',
            0,
            attributes: ['last_error' => 'Previous timeout']
        );
        $queuedWithLockEvidence = $this->insertPost(
            $campaignId,
            $campaignDomainId,
            'queued',
            0,
            attributes: ['lock_token' => 'stale-worker']
        );
        $createdWithTitleOnly = $this->insertPost(
            $campaignId,
            $campaignDomainId,
            'failed',
            0,
            attributes: ['remote_title' => 'Published title']
        );

        $migration = require database_path('migrations/2026_07_19_020000_add_safe_campaign_domain_replacement_schema.php');
        $migration->up();

        $this->assertSame('not_attempted', DB::table('campaign_posts')->find($notAttempted)->delivery_state);
        $this->assertSame('remote_unknown', DB::table('campaign_posts')->find($attempted)->delivery_state);
        $this->assertSame('remote_unknown', DB::table('campaign_posts')->find($publishing)->delivery_state);
        $this->assertSame('remote_created', DB::table('campaign_posts')->find($created)->delivery_state);
        $this->assertSame(
            'remote_unknown',
            DB::table('campaign_posts')->find($queuedWithRetryEvidence)->delivery_state
        );
        $this->assertSame(
            'remote_unknown',
            DB::table('campaign_posts')->find($queuedWithLockEvidence)->delivery_state
        );
        $this->assertSame(
            'remote_created',
            DB::table('campaign_posts')->find($createdWithTitleOnly)->delivery_state
        );
        $this->assertTrue(Schema::hasColumns('campaign_posts', [
            'delivery_state',
            'last_failure_code',
            'dispatch_generation',
        ]));
        $this->assertTrue(Schema::hasTable('campaign_domain_replacements'));

        $adminId = DB::table('admins')->insertGetId([]);
        $domainId = DB::table('domains')->insertGetId([]);
        $auditId = DB::table('campaign_domain_replacements')->insertGetId([
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'campaign_id' => $campaignId,
            'campaign_post_id' => $notAttempted,
            'campaign_domain_id' => $campaignDomainId,
            'old_domain_id' => $domainId,
            'new_domain_id' => $domainId,
            'old_hostname' => 'old.example',
            'new_hostname' => 'new.example',
            'admin_id' => $adminId,
            'previous_status' => 'queued',
            'dispatch_generation' => 1,
            'state' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('campaign_posts')->where('id', $notAttempted)->delete();
        DB::table('campaign_domains')->where('id', $campaignDomainId)->delete();
        DB::table('campaigns')->where('id', $campaignId)->delete();

        $audit = DB::table('campaign_domain_replacements')->find($auditId);
        $this->assertNotNull($audit);
        $this->assertNull($audit->campaign_id);
        $this->assertNull($audit->campaign_post_id);
        $this->assertNull($audit->campaign_domain_id);

        $migration->down();

        $this->assertFalse(Schema::hasTable('campaign_domain_replacements'));
        $this->assertFalse(Schema::hasColumn('campaign_posts', 'delivery_state'));
    }

    private function insertPost(
        int $campaignId,
        int $campaignDomainId,
        string $status,
        int $attemptCount,
        ?string $remoteId = null,
        array $attributes = [],
    ): int {
        return DB::table('campaign_posts')->insertGetId([
            'campaign_id' => $campaignId,
            'campaign_domain_id' => $campaignDomainId,
            'status' => $status,
            'remote_id' => $remoteId,
            'attempt_count' => $attemptCount,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }
}
