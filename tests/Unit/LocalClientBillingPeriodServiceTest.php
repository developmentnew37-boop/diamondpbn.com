<?php

namespace Tests\Unit;

use App\Models\Admin\Campaign;
use App\Models\Admin\LocalClient;
use App\Services\LocalClientBillingPeriodService;
use App\Services\LocalClientPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalClientBillingPeriodServiceTest extends TestCase
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
            $table->tinyInteger('type')->default(0);
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

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token', 64);
            $table->unsignedBigInteger('admin_id')->default(1);
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('local_client_billing_periods');
        Schema::dropIfExists('local_client_bill_lines');
        Schema::dropIfExists('local_client_payment_events');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_builds_one_open_unpaid_period_with_correct_totals(): void
    {
        $client = LocalClient::create([
            'name' => 'Client A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('a', 64),
            'created_by_admin_id' => 1,
        ]);

        $firstCampaign = Campaign::create([
            'campaign_no' => 'cmp-a',
            'report_token' => str_repeat('b', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 100,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '100.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $firstCampaign->forceFill(['created_at' => Carbon::parse('2026-08-03 10:00:00')])->saveQuietly();

        $secondCampaign = Campaign::create([
            'campaign_no' => 'cmp-b',
            'report_token' => str_repeat('c', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 60,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '60.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $secondCampaign->forceFill(['created_at' => Carbon::parse('2026-08-05 12:00:00')])->saveQuietly();

        $periods = app(LocalClientBillingPeriodService::class)->periodsForClient($client);

        $this->assertCount(1, $periods);
        $open = $periods->first();
        $this->assertTrue($open['is_open']);
        $this->assertSame('unpaid', $open['status']);
        $this->assertSame(2, $open['campaign_count']);
        $this->assertSame(160.0, $open['total_amount']);
        $this->assertNull($open['period_end']);
        $this->assertSame('2026-08-03', $open['period_start']->toDateString());
    }

    public function test_bulk_pay_closes_period_and_new_unpaid_campaign_opens_new_period(): void
    {
        Carbon::setTestNow('2026-08-06 09:00:00');

        $client = LocalClient::create([
            'name' => 'Client A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('d', 64),
            'created_by_admin_id' => 1,
        ]);

        $first = Campaign::create([
            'campaign_no' => 'cmp-old-1',
            'report_token' => str_repeat('e', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 100,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '100.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $first->forceFill(['created_at' => Carbon::parse('2026-08-03 10:00:00')])->saveQuietly();

        $second = Campaign::create([
            'campaign_no' => 'cmp-old-2',
            'report_token' => str_repeat('f', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 60,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '60.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $second->forceFill(['created_at' => Carbon::parse('2026-08-05 12:00:00')])->saveQuietly();

        $admin = new \App\Models\Admin(['id' => 1, 'name' => 'Super', 'slug' => 'super', 'email' => 's@test.com', 'password' => 'x', 'type' => 0]);
        $admin->exists = true;

        app(LocalClientPaymentService::class)->markOpenPeriodPaid($client, $admin, 'Bulk settlement');

        $newCampaign = Campaign::create([
            'campaign_no' => 'cmp-new',
            'report_token' => str_repeat('g', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 40,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '40.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $newCampaign->forceFill(['created_at' => Carbon::parse('2026-08-05 18:00:00')])->saveQuietly();

        $periods = app(LocalClientBillingPeriodService::class)->periodsForClient($client);

        $this->assertCount(2, $periods);

        $open = $periods->first();
        $this->assertTrue($open['is_open']);
        $this->assertSame(1, $open['campaign_count']);
        $this->assertSame(40.0, $open['total_amount']);
        $this->assertSame('2026-08-05', $open['period_start']->toDateString());

        $closed = $periods->last();
        $this->assertFalse($closed['is_open']);
        $this->assertSame('paid', $closed['status']);
        $this->assertSame(2, $closed['campaign_count']);
        $this->assertSame(160.0, $closed['total_amount']);
        $this->assertSame('2026-08-03', $closed['period_start']->toDateString());
        $this->assertSame('2026-08-05', $closed['period_end']->toDateString());

        $first->refresh();
        $second->refresh();
        $this->assertSame('paid', $first->billing_payment_status);
        $this->assertSame('paid', $second->billing_payment_status);
        $this->assertSame($first->billing_paid_at?->toDateTimeString(), $second->billing_paid_at?->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_paid_campaigns_with_different_paid_at_timestamps_form_separate_periods(): void
    {
        $client = LocalClient::create([
            'name' => 'Client A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('h', 64),
            'created_by_admin_id' => 1,
        ]);

        $olderPaid = Campaign::create([
            'campaign_no' => 'cmp-1',
            'report_token' => str_repeat('i', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 50,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '50.00', 'lines' => []],
            'billing_payment_status' => 'paid',
            'billing_paid_at' => Carbon::parse('2026-08-01 09:00:00'),
        ]);
        $olderPaid->forceFill(['created_at' => Carbon::parse('2026-07-28 10:00:00')])->saveQuietly();

        $newerPaid = Campaign::create([
            'campaign_no' => 'cmp-2',
            'report_token' => str_repeat('j', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 75,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '75.00', 'lines' => []],
            'billing_payment_status' => 'paid',
            'billing_paid_at' => Carbon::parse('2026-08-10 09:00:00'),
        ]);
        $newerPaid->forceFill(['created_at' => Carbon::parse('2026-08-08 10:00:00')])->saveQuietly();

        $periods = app(LocalClientBillingPeriodService::class)->periodsForClient($client);

        $this->assertCount(2, $periods);
        $this->assertFalse($periods->first()['is_open']);
        $this->assertFalse($periods->last()['is_open']);
        $this->assertSame(75.0, $periods->first()['total_amount']);
        $this->assertSame(50.0, $periods->last()['total_amount']);
    }

    public function test_frozen_paid_period_keeps_totals_after_campaign_delete(): void
    {
        Carbon::setTestNow('2026-08-06 09:00:00');

        $client = LocalClient::create([
            'name' => 'Client A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('k', 64),
            'created_by_admin_id' => 1,
        ]);

        $first = Campaign::create([
            'campaign_no' => 'cmp-freeze-1',
            'report_token' => str_repeat('l', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 100,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '100.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $first->forceFill(['created_at' => Carbon::parse('2026-08-03 10:00:00')])->saveQuietly();

        $second = Campaign::create([
            'campaign_no' => 'cmp-freeze-2',
            'report_token' => str_repeat('m', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 60,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '60.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $second->forceFill(['created_at' => Carbon::parse('2026-08-05 12:00:00')])->saveQuietly();

        $admin = new \App\Models\Admin(['id' => 1, 'name' => 'Super', 'slug' => 'super', 'email' => 's@test.com', 'password' => 'x', 'type' => 0]);
        $admin->exists = true;

        app(LocalClientPaymentService::class)->markOpenPeriodPaid($client, $admin);

        $first->delete();

        $periods = app(LocalClientBillingPeriodService::class)->periodsForClient($client);
        $paidPeriod = $periods->first(fn (array $period) => $period['status'] === 'paid');

        $this->assertNotNull($paidPeriod);
        $this->assertSame(2, $paidPeriod['campaign_count']);
        $this->assertSame(160.0, $paidPeriod['total_amount']);

        Carbon::setTestNow();
    }

    public function test_bill_lines_are_removed_when_billable_campaign_is_deleted(): void
    {
        $client = LocalClient::create([
            'name' => 'Client A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('n', 64),
            'created_by_admin_id' => 1,
        ]);

        $campaign = Campaign::create([
            'campaign_no' => 'cmp-cleanup',
            'report_token' => str_repeat('o', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 50,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '50.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);

        \App\Models\Admin\LocalClientBillLine::create([
            'local_client_id' => $client->id,
            'billing_campaign_type' => 'post',
            'unit_price' => 50,
            'line_total' => 50,
            'currency' => 'USD',
            'billable_type' => Campaign::class,
            'billable_id' => $campaign->id,
            'campaign_no' => $campaign->campaign_no,
            'snapshot' => [],
            'created_at' => now(),
        ]);

        $this->assertSame(1, \App\Models\Admin\LocalClientBillLine::count());

        $campaign->delete();

        $this->assertSame(0, \App\Models\Admin\LocalClientBillLine::count());
    }
}
