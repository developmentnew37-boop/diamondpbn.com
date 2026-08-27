<?php

namespace Tests\Unit;

use App\Data\AgentStatusResult;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillLine;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Services\CampaignDomainReplacementService;
use App\Services\WordPressAgentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CampaignDomainReplacementBillingTest extends TestCase
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
        Schema::create('domain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('admin_id')->default(1);
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
        Schema::create('local_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->string('billing_report_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id');
            $table->timestamps();
        });
        Schema::create('local_client_domain_category_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->unsignedBigInteger('domain_category_id');
            $table->decimal('post_price', 12, 2)->nullable();
            $table->decimal('sidebar_price', 12, 2)->nullable();
            $table->decimal('hidden_links_price', 12, 2)->nullable();
            $table->decimal('sticky_price', 12, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('local_client_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->string('billing_campaign_type', 32);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->string('campaign_no', 64)->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->nullable();
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
            $table->unsignedBigInteger('local_client_id')->nullable();
            $table->decimal('billing_total', 12, 2)->nullable();
            $table->string('billing_currency', 3)->nullable();
            $table->json('billing_snapshot')->nullable();
            $table->string('billing_payment_status')->nullable();
            $table->timestamp('billing_paid_at')->nullable();
            $table->unsignedBigInteger('billing_paid_by_admin_id')->nullable();
            $table->text('billing_payment_note')->nullable();
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

        DB::table('domain_categories')->insert([
            ['id' => 10, 'name' => 'Cat A', 'slug' => 'cat-a', 'admin_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Cat B', 'slug' => 'cat-b', 'admin_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaign_domain_replacements');
        Schema::dropIfExists('campaign_posts');
        Schema::dropIfExists('campaign_articles');
        Schema::dropIfExists('campaign_domains');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('local_client_bill_lines');
        Schema::dropIfExists('local_client_domain_category_prices');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('domain_categories');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_unpaid_replace_recalculates_billing_total(): void
    {
        Queue::fake();
        $fixture = $this->billedFixture(paymentStatus: 'unpaid', oldPrice: 15, newPrice: 20);

        $this->service()->replace(
            $fixture['post'],
            $fixture['admin'],
            $fixture['new_domain_id'],
            $fixture['old_domain_id'],
            (string) Str::uuid(),
            'Dead domain'
        );

        $campaign = $fixture['campaign']->fresh();
        $this->assertSame('20.00', (string) $campaign->billing_total);
        $this->assertSame('unpaid', $campaign->billing_payment_status);
        $this->assertSame(1, LocalClientBillLine::count());
        $this->assertSame('20.00', (string) LocalClientBillLine::first()->unit_price);
        $this->assertSame($fixture['new_domain_id'], (int) LocalClientBillLine::first()->domain_id);
    }

    public function test_paid_replace_keeps_billing_frozen(): void
    {
        Queue::fake();
        $fixture = $this->billedFixture(paymentStatus: 'paid', oldPrice: 15, newPrice: 20);

        $this->service()->replace(
            $fixture['post'],
            $fixture['admin'],
            $fixture['new_domain_id'],
            $fixture['old_domain_id'],
            (string) Str::uuid(),
            'Dead domain'
        );

        $campaign = $fixture['campaign']->fresh();
        $this->assertSame('15.00', (string) $campaign->billing_total);
        $this->assertSame('paid', $campaign->billing_payment_status);
        $this->assertSame($fixture['old_domain_id'], (int) LocalClientBillLine::first()->domain_id);
        $this->assertSame($fixture['new_domain_id'], (int) DB::table('campaign_domains')->find($fixture['campaign_domain_id'])->domain_id);
    }

    public function test_replace_without_local_client_still_works(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $oldDomainId = $this->domain($admin->id, 10, 'old.example');
        $newDomainId = $this->domain($admin->id, 20, 'new.example');

        $campaignId = DB::table('campaigns')->insertGetId([
            'campaign_no' => 'CMP-NO-CLIENT',
            'report_token' => Str::random(64),
            'domain_category_id' => 10,
            'admin_id' => $admin->id,
            'status' => 'failed',
            'is_sticky_campaign' => false,
            'total_targets' => 1,
            'failed_targets' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaignDomainId = DB::table('campaign_domains')->insertGetId([
            'campaign_id' => $campaignId,
            'domain_id' => $oldDomainId,
            'sort_order' => 0,
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
            'status' => 'failed',
            'delivery_state' => CampaignPost::DELIVERY_REMOTE_ABSENT,
            'dispatch_generation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $replacement = $this->service()->replace(
            CampaignPost::findOrFail($postId),
            $admin,
            $newDomainId,
            $oldDomainId,
            (string) Str::uuid(),
            'No billing'
        );

        $this->assertSame('completed', $replacement->state);
        $this->assertSame($newDomainId, (int) DB::table('campaign_domains')->find($campaignDomainId)->domain_id);
        $this->assertNull(Campaign::find($campaignId)->local_client_id);
        Queue::assertPushed(PublishCampaignPostJob::class, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function billedFixture(string $paymentStatus, float $oldPrice, float $newPrice): array
    {
        $admin = $this->admin();
        $client = LocalClient::create([
            'name' => 'Billing Client',
            'default_currency' => 'USD',
            'billing_report_token' => Str::random(64),
            'created_by_admin_id' => $admin->id,
        ]);

        LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 10,
            'post_price' => $oldPrice,
        ]);
        LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 20,
            'post_price' => $newPrice,
        ]);

        $oldDomainId = $this->domain($admin->id, 10, 'old.example');
        $newDomainId = $this->domain($admin->id, 20, 'new.example');

        $campaignId = DB::table('campaigns')->insertGetId([
            'campaign_no' => 'CMP-BILL',
            'report_token' => Str::random(64),
            'domain_category_id' => 10,
            'admin_id' => $admin->id,
            'status' => 'failed',
            'is_sticky_campaign' => false,
            'total_targets' => 1,
            'failed_targets' => 1,
            'local_client_id' => $client->id,
            'billing_total' => number_format($oldPrice, 2, '.', ''),
            'billing_currency' => 'USD',
            'billing_snapshot' => json_encode([
                'total' => number_format($oldPrice, 2, '.', ''),
                'currency' => 'USD',
                'lines' => [[
                    'domain_id' => $oldDomainId,
                    'domain_name' => 'old.example',
                    'category_id' => 10,
                    'unit_price' => number_format($oldPrice, 2, '.', ''),
                ]],
            ]),
            'billing_payment_status' => $paymentStatus,
            'billing_paid_at' => $paymentStatus === 'paid' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LocalClientBillLine::insert([
            'local_client_id' => $client->id,
            'domain_id' => $oldDomainId,
            'domain_category_id' => 10,
            'billing_campaign_type' => 'post',
            'unit_price' => number_format($oldPrice, 2, '.', ''),
            'line_total' => number_format($oldPrice, 2, '.', ''),
            'currency' => 'USD',
            'billable_type' => Campaign::class,
            'billable_id' => $campaignId,
            'campaign_no' => 'CMP-BILL',
            'snapshot' => json_encode(['domain_id' => $oldDomainId]),
            'created_at' => now(),
        ]);

        $campaignDomainId = DB::table('campaign_domains')->insertGetId([
            'campaign_id' => $campaignId,
            'domain_id' => $oldDomainId,
            'sort_order' => 0,
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
            'status' => 'failed',
            'delivery_state' => CampaignPost::DELIVERY_REMOTE_ABSENT,
            'dispatch_generation' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'admin' => $admin,
            'campaign' => Campaign::findOrFail($campaignId),
            'post' => CampaignPost::findOrFail($postId),
            'old_domain_id' => $oldDomainId,
            'new_domain_id' => $newDomainId,
            'campaign_domain_id' => $campaignDomainId,
        ];
    }

    private function service(): CampaignDomainReplacementService
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

    private function admin(): Admin
    {
        $id = DB::table('admins')->insertGetId([
            'name' => 'Owner',
            'slug' => 'owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('password'),
            'type' => Admin::ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Admin::findOrFail($id);
    }

    private function domain(int $adminId, int $categoryId, string $name): int
    {
        return DB::table('domains')->insertGetId([
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
