<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientDeletedCampaignBilling;
use App\Services\LocalClientBillingPeriodService;
use App\Services\LocalClientBillingReportService;
use App\Services\LocalClientPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalClientDeletedCampaignBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('local_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('billing_report_token', 64)->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->default(1);
            $table->timestamps();
        });

        Schema::create('local_client_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
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

        Schema::create('local_client_billing_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('campaign_count');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by_admin_id')->nullable();
            $table->text('payment_note')->nullable();
            $table->timestamps();
        });

        Schema::create('local_client_deleted_campaign_billings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->string('campaign_type', 64);
            $table->string('campaign_no', 191)->nullable();
            $table->decimal('billing_total', 12, 2);
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
            $table->string('billing_currency', 3)->default('USD');
            $table->string('billing_payment_status', 32);
            $table->timestamp('billing_paid_at')->nullable();
            $table->timestamp('campaign_created_at')->nullable();
            $table->timestamps();
            $table->unique(['billable_type', 'billable_id']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token', 64)->nullable();
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedBigInteger('local_client_id')->nullable();
            $table->decimal('billing_total', 12, 2)->nullable();
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
            $table->string('billing_currency', 3)->nullable();
            $table->json('billing_snapshot')->nullable();
            $table->string('billing_payment_status')->nullable();
            $table->timestamp('billing_paid_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('local_client_deleted_campaign_billings');
        Schema::dropIfExists('local_client_billing_periods');
        Schema::dropIfExists('local_client_payment_events');
        Schema::dropIfExists('local_client_bill_lines');
        Schema::dropIfExists('local_clients');

        parent::tearDown();
    }

    public function test_paid_campaign_stays_on_the_billing_report_after_delete(): void
    {
        $client = LocalClient::query()->create([
            'name' => 'Acme',
            'created_by_admin_id' => 1,
        ]);

        $paid = Campaign::query()->create([
            'campaign_no' => 'CMP-PAID',
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 40,
            'billing_amount_paid' => 40,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['lines' => 1],
            'billing_payment_status' => 'paid',
            'billing_paid_at' => now(),
        ]);

        $unpaid = Campaign::query()->create([
            'campaign_no' => 'CMP-UNPAID',
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 15,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['lines' => 1],
            'billing_payment_status' => 'unpaid',
        ]);

        $paid->delete();
        $unpaid->delete();

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame(1, LocalClientDeletedCampaignBilling::query()->count());

        $archive = LocalClientDeletedCampaignBilling::query()->first();
        $this->assertSame('CMP-PAID', $archive->campaign_no);
        $this->assertSame('paid', $archive->billing_payment_status);
        $this->assertSame('40.00', (string) $archive->billing_total);

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['campaign_deleted']);
        $this->assertNull($rows[0]['report_url']);
        $this->assertSame('paid', $rows[0]['billing_payment_status']);
        $this->assertSame('CMP-PAID', $rows[0]['campaign_no']);
    }

    public function test_deleted_semi_paid_campaign_is_closed_when_the_open_period_is_marked_paid(): void
    {
        $client = LocalClient::query()->create([
            'name' => 'Acme',
            'default_currency' => 'USD',
            'created_by_admin_id' => 1,
        ]);

        $campaign = Campaign::query()->create([
            'campaign_no' => 'CMP-SEMI',
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 100,
            'billing_amount_paid' => 40,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['lines' => 1],
            'billing_payment_status' => 'semi_paid',
        ]);

        $campaign->delete();

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['campaign_deleted']);
        $this->assertSame('semi_paid', $rows[0]['billing_payment_status']);
        $this->assertSame('60.00', $rows[0]['billing_balance_due']);

        $open = app(LocalClientBillingPeriodService::class)
            ->periodsForClient($client)
            ->first(fn (array $period) => $period['is_open']);
        $this->assertNotNull($open);
        $this->assertSame(60.0, $open['total_amount']);

        $admin = new Admin([
            'id' => 1,
            'name' => 'Super',
            'slug' => 'super',
            'email' => 's@test.com',
            'password' => 'x',
            'type' => 0,
        ]);
        $admin->exists = true;

        $result = app(LocalClientPaymentService::class)->markOpenPeriodPaid($client, $admin);

        $this->assertSame(1, $result['updated_count']);

        $archive = LocalClientDeletedCampaignBilling::query()->first();
        $this->assertSame('paid', $archive->billing_payment_status);
        $this->assertNull($archive->billing_amount_paid);

        $periods = app(LocalClientBillingPeriodService::class)->periodsForClient($client);
        $this->assertFalse($periods->contains(fn (array $period) => $period['is_open']));

        $paidPeriod = $periods->first(fn (array $period) => $period['status'] === 'paid');
        $this->assertNotNull($paidPeriod);
        $this->assertSame(1, $paidPeriod['campaign_count']);
        $this->assertSame(60.0, $paidPeriod['total_amount']);

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['campaign_deleted']);
        $this->assertSame('paid', $rows[0]['billing_payment_status']);
    }

    public function test_long_campaign_number_is_kept_when_a_paid_campaign_is_deleted(): void
    {
        $client = LocalClient::query()->create([
            'name' => 'Acme',
            'created_by_admin_id' => 1,
        ]);

        $campaignNo = str_repeat('C', 100);

        $paid = Campaign::query()->create([
            'campaign_no' => $campaignNo,
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 25,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['lines' => 1],
            'billing_payment_status' => 'paid',
            'billing_paid_at' => now(),
        ]);

        $paid->delete();

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame($campaignNo, LocalClientDeletedCampaignBilling::query()->value('campaign_no'));
    }

    public function test_archive_is_hidden_while_the_campaign_still_exists(): void
    {
        $client = LocalClient::query()->create([
            'name' => 'Acme',
            'created_by_admin_id' => 1,
        ]);

        $campaign = Campaign::query()->create([
            'campaign_no' => 'CMP-LIVE',
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 40,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['lines' => 1],
            'billing_payment_status' => 'paid',
            'billing_paid_at' => now(),
        ]);

        LocalClientDeletedCampaignBilling::query()->create([
            'local_client_id' => $client->id,
            'billable_type' => Campaign::class,
            'billable_id' => $campaign->id,
            'campaign_type' => 'campaign',
            'campaign_no' => 'CMP-LIVE',
            'billing_total' => 40,
            'billing_currency' => 'USD',
            'billing_payment_status' => 'paid',
            'campaign_created_at' => $campaign->created_at,
        ]);

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['campaign_deleted']);
        $this->assertSame('CMP-LIVE', $rows[0]['campaign_no']);
    }
}
