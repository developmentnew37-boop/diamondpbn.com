<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Services\LocalClientPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalClientBillingFeatureTest extends TestCase
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

        Schema::create('domain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('admin_id')->default(1);
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

        Schema::create('local_client_payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->unsignedBigInteger('local_client_id')->nullable();
            $table->string('campaign_no', 64)->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token', 64);
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
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

        Admin::unguard();
        Admin::create(['id' => 1, 'name' => 'Super', 'slug' => 'super', 'email' => 's@test.com', 'password' => 'x', 'type' => 0]);
        Admin::create(['id' => 2, 'name' => 'Admin', 'slug' => 'admin', 'email' => 'a@test.com', 'password' => 'x', 'type' => 1]);
        Admin::reguard();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('local_client_payment_events');
        Schema::dropIfExists('local_client_bill_lines');
        Schema::dropIfExists('local_client_domain_category_prices');
        Schema::dropIfExists('campaign_domains');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('domain_categories');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_public_client_report_requires_valid_token(): void
    {
        $client = LocalClient::create([
            'name' => 'Client A',
            'default_currency' => 'EUR',
            'billing_report_token' => str_repeat('t', 64),
            'created_by_admin_id' => 1,
        ]);

        $this->get('/client/billing/'.$client->id.'/bad-token')->assertNotFound();
        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token)
            ->assertOk()
            ->assertSee('Client A');
    }

    public function test_admin_cannot_mark_paid_on_foreign_campaign(): void
    {
        $client = LocalClient::create([
            'name' => 'Client A',
            'billing_report_token' => str_repeat('u', 64),
            'created_by_admin_id' => 1,
        ]);

        $campaign = Campaign::create([
            'campaign_no' => 'CMP-1',
            'report_token' => str_repeat('v', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 50,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '50.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);

        $admin = Admin::find(2);
        $service = app(LocalClientPaymentService::class);

        $this->expectException(ValidationException::class);
        $service->toggle($campaign, 'paid', $admin);
    }

    public function test_super_admin_can_mark_campaign_paid(): void
    {
        $client = LocalClient::create([
            'name' => 'Client A',
            'billing_report_token' => str_repeat('w', 64),
            'created_by_admin_id' => 1,
        ]);

        $campaign = Campaign::create([
            'campaign_no' => 'CMP-2',
            'report_token' => str_repeat('x', 64),
            'admin_id' => 2,
            'local_client_id' => $client->id,
            'billing_total' => 50,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '50.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);

        $super = Admin::find(1);
        app(LocalClientPaymentService::class)->toggle($campaign, 'paid', $super, 'Bank ref 123');

        $campaign->refresh();
        $this->assertSame('paid', $campaign->billing_payment_status);
        $this->assertSame(1, LocalClientPaymentEvent::count());
    }

    public function test_local_client_show_displays_billing_periods_not_campaign_rows(): void
    {
        $client = LocalClient::create([
            'name' => 'Client Period',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('p', 64),
            'created_by_admin_id' => 1,
        ]);

        Campaign::create([
            'campaign_no' => 'cmp-period-1',
            'report_token' => str_repeat('q', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 50,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '50.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
            'created_at' => now()->subDays(2),
        ]);

        $periods = app(\App\Services\LocalClientBillingPeriodService::class)->periodsForClient($client);

        $this->assertCount(1, $periods);
        $this->assertTrue($periods->first()['is_open']);
    }

    public function test_mark_open_period_paid_updates_all_unpaid_campaigns(): void
    {
        $client = LocalClient::create([
            'name' => 'Client Bulk',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('r', 64),
            'created_by_admin_id' => 1,
        ]);

        Campaign::create([
            'campaign_no' => 'cmp-bulk-1',
            'report_token' => str_repeat('s', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 30,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '30.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);

        Campaign::create([
            'campaign_no' => 'cmp-bulk-2',
            'report_token' => str_repeat('t', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 20,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '20.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);

        $super = Admin::find(1);
        $result = app(LocalClientPaymentService::class)->markOpenPeriodPaid($client, $super, 'Settled');

        $this->assertSame(2, $result['updated_count']);
        $this->assertSame(2, Campaign::where('billing_payment_status', 'paid')->count());
        $this->assertSame(2, LocalClientPaymentEvent::count());

        $periods = app(\App\Services\LocalClientBillingPeriodService::class)->periodsForClient($client);
        $this->assertCount(1, $periods);
        $this->assertFalse($periods->first()['is_open']);
        $this->assertSame('paid', $periods->first()['status']);
    }

    public function test_public_client_report_shows_campaign_report_links(): void
    {
        $client = LocalClient::create([
            'name' => 'Client Reports',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('z', 64),
            'created_by_admin_id' => 1,
        ]);

        $reportToken = str_repeat('y', 64);

        $campaign = new Campaign([
            'campaign_no' => 'cmp-report-link-1',
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 50,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '50.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        // report_token is not mass-assignable; set explicitly so the expected URL is stable.
        $campaign->report_token = $reportToken;
        $campaign->save();

        $expectedReportUrl = route('admin.campaign.report', [
            'campaign_no' => 'cmp-report-link-1',
            'token' => $reportToken,
        ]);

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token)
            ->assertOk()
            ->assertSee('View report')
            ->assertSee($expectedReportUrl, false);
    }

    public function test_sync_billing_recalculates_total_and_marks_paid_unpaid(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('domain_category_id');
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->integer('status')->default(1);
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
        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        \DB::table('domain_categories')->insert([
            ['id' => 1, 'name' => 'A', 'slug' => 'a', 'admin_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'B', 'slug' => 'b', 'admin_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $client = LocalClient::create([
            'name' => 'Sync Route Client',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('s', 64),
            'created_by_admin_id' => 1,
        ]);

        \App\Models\Admin\LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 1,
            'post_price' => 15,
        ]);
        \App\Models\Admin\LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 2,
            'post_price' => 20,
        ]);

        $domain = \App\Models\Admin\Domain::create([
            'name' => 'sync-route.com',
            'domain_category_id' => 2,
            'admin_id' => 1,
        ]);

        $campaign = Campaign::create([
            'campaign_no' => 'cmp-sync-route',
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 15,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '15.00', 'lines' => []],
            'billing_payment_status' => 'paid',
            'billing_paid_at' => now(),
        ]);
        $campaign->report_token = str_repeat('r', 64);
        $campaign->save();

        \App\Models\Admin\CampaignDomain::create([
            'campaign_id' => $campaign->id,
            'domain_id' => $domain->id,
            'sort_order' => 0,
        ]);

        $this->actingAs(Admin::find(1), 'admin')
            ->from('/admin/campaign/'.$campaign->id)
            ->post(route('admin.local-clients.billing.sync', [
                'billableType' => 'campaign',
                'id' => $campaign->id,
            ]))
            ->assertRedirect('/admin/campaign/'.$campaign->id)
            ->assertSessionHas('cus__success');

        $campaign->refresh();
        $this->assertSame('20.00', (string) $campaign->billing_total);
        $this->assertSame('unpaid', $campaign->billing_payment_status);
    }
}
