<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\LocalClientController;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Models\Admin\LocalClientDeletedCampaignBilling;
use App\Models\Admin\LocalClientInstallment;
use App\Models\Admin\LocalClientInstallmentAllocation;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Models\Admin\SidebarCampaign;
use App\Services\LocalClientBillingReportService;
use App\Services\LocalClientPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalClientInstallmentTest extends TestCase
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

        Schema::create('local_client_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('source', 32)->default('payment');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('local_client_installment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('installment_id');
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->unsignedBigInteger('deleted_campaign_billing_id')->nullable();
            $table->string('campaign_no', 191)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('previous_collected_amount', 12, 2);
            $table->decimal('resulting_collected_amount', 12, 2);
            $table->decimal('billing_total', 12, 2);
            $table->string('old_status', 32);
            $table->string('new_status', 32);
            $table->timestamp('created_at')->nullable();
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
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
            $table->string('billing_currency', 3)->nullable();
            $table->json('billing_snapshot')->nullable();
            $table->string('billing_payment_status')->nullable();
            $table->timestamp('billing_paid_at')->nullable();
            $table->unsignedBigInteger('billing_paid_by_admin_id')->nullable();
            $table->text('billing_payment_note')->nullable();
            $table->timestamps();
        });

        Schema::create('sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token', 64);
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->string('status')->default('queued');
            $table->unsignedBigInteger('local_client_id')->nullable();
            $table->decimal('billing_total', 12, 2)->nullable();
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
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
        Schema::dropIfExists('sidebar_campaigns');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('local_client_deleted_campaign_billings');
        Schema::dropIfExists('local_client_installment_allocations');
        Schema::dropIfExists('local_client_installments');
        Schema::dropIfExists('local_client_billing_periods');
        Schema::dropIfExists('local_client_payment_events');
        Schema::dropIfExists('local_clients');
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_installment_pays_oldest_campaigns_and_leaves_the_remainder_semi_paid(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 5);
        $admin = $this->admin();

        $result = app(LocalClientPaymentService::class)->recordInstallment($client, 700, $admin, 'First payment');

        $this->assertSame(3, $result['paid_count']);
        $this->assertSame(1, $result['semi_paid_count']);
        $this->assertSame(0, LocalClientBillingPeriod::query()->count());

        foreach ([0, 1, 2] as $index) {
            $campaigns[$index]->refresh();
            $this->assertSame('paid', $campaigns[$index]->billing_payment_status);
            $this->assertNull($campaigns[$index]->billing_amount_paid);
            $this->assertNotNull($campaigns[$index]->billing_paid_at);
        }

        $campaigns[3]->refresh();
        $this->assertSame('semi_paid', $campaigns[3]->billing_payment_status);
        $this->assertSame(10000, LocalClientPaymentService::toCents($campaigns[3]->billing_amount_paid));
        $this->assertNull($campaigns[3]->billing_paid_at);

        $campaigns[4]->refresh();
        $this->assertSame('unpaid', $campaigns[4]->billing_payment_status);
        $this->assertNull($campaigns[4]->billing_amount_paid);
    }

    public function test_next_installment_continues_from_the_semi_paid_remainder(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 5);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 700, $admin);
        $service->recordInstallment($client, 150, $admin, 'Second payment');

        $campaigns[3]->refresh();
        $campaigns[4]->refresh();

        $this->assertSame('paid', $campaigns[3]->billing_payment_status);
        $this->assertNull($campaigns[3]->billing_amount_paid);
        $this->assertSame('semi_paid', $campaigns[4]->billing_payment_status);
        $this->assertSame(5000, LocalClientPaymentService::toCents($campaigns[4]->billing_amount_paid));

        $service->voidLatestInstallment($client, $admin);

        $campaigns[3]->refresh();
        $campaigns[4]->refresh();

        $this->assertSame('semi_paid', $campaigns[3]->billing_payment_status);
        $this->assertSame(10000, LocalClientPaymentService::toCents($campaigns[3]->billing_amount_paid));
        $this->assertSame('unpaid', $campaigns[4]->billing_payment_status);
        $this->assertSame(1, LocalClientInstallment::query()->count());
    }

    public function test_overpayment_is_rejected_and_a_second_currency_is_left_untouched(): void
    {
        $client = $this->client();
        $olderEuro = $this->makeCampaign($client, 'euro', '2026-08-01 09:00:00', 200, 'EUR');
        $usd = $this->makeCampaign($client, 'usd', '2026-08-02 09:00:00', 200, 'USD');
        $service = app(LocalClientPaymentService::class);

        try {
            $service->recordInstallment($client, 400, $this->admin());
            $this->fail('Expected the installment to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $service->recordInstallment($client, 200, $this->admin());

        $olderEuro->refresh();
        $usd->refresh();
        $this->assertSame('unpaid', $olderEuro->billing_payment_status);
        $this->assertSame('paid', $usd->billing_payment_status);
    }

    public function test_a_paid_period_can_be_marked_unpaid(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 2);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->markOpenPeriodPaid($client, $admin, 'Settled');

        $campaigns[0]->refresh();
        $this->assertSame('paid', $campaigns[0]->billing_payment_status);
        $this->assertSame(1, LocalClientBillingPeriod::query()->count());

        $result = $service->markPaidPeriodUnpaid($client, $campaigns[0]->billing_paid_at, $admin);

        $this->assertSame(2, $result['updated_count']);
        $this->assertSame(0, $result['skipped_count']);

        foreach ($campaigns as $campaign) {
            $campaign->refresh();
            $this->assertSame('unpaid', $campaign->billing_payment_status);
            $this->assertNull($campaign->billing_paid_at);
        }

        $this->assertSame(0, LocalClientBillingPeriod::query()->count());
    }

    public function test_marking_an_installment_payment_unpaid_is_refused(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 1);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 200, $admin);
        $campaigns[0]->refresh();

        $this->expectException(ValidationException::class);
        $service->markPaidPeriodUnpaid($client, $campaigns[0]->billing_paid_at, $admin);
    }

    public function test_unpaid_toggle_is_blocked_when_an_installment_was_applied(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 4);
        $service = app(LocalClientPaymentService::class);
        $service->recordInstallment($client, 700, $this->admin());

        $campaigns[3]->refresh();

        $this->expectException(ValidationException::class);
        $service->toggle($campaigns[3], 'unpaid', $this->admin());
    }

    public function test_void_restores_campaigns_from_the_latest_installment_only(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 4);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 700, $admin);
        $service->voidLatestInstallment($client, $admin);

        foreach ($campaigns as $campaign) {
            $campaign->refresh();
            $this->assertSame('unpaid', $campaign->billing_payment_status);
            $this->assertNull($campaign->billing_amount_paid);
        }

        $this->assertSame(0, LocalClientInstallment::query()->count());
    }

    public function test_revert_clears_a_remainder_after_cash_was_moved_onto_it(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 6);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 700, $admin);

        $campaigns[3]->refresh();
        $this->assertSame('semi_paid', $campaigns[3]->billing_payment_status);
        $this->assertSame(10000, LocalClientPaymentService::toCents($campaigns[3]->billing_amount_paid));

        $service->toggle($campaigns[0]->fresh(), 'unpaid', $admin);

        $remainderRows = LocalClientInstallmentAllocation::query()
            ->where('billable_id', $campaigns[3]->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $remainderRows);
        $this->assertSame(0, LocalClientPaymentService::toCents($remainderRows[0]->previous_collected_amount));
        $this->assertSame(10000, LocalClientPaymentService::toCents($remainderRows[1]->previous_collected_amount));

        $result = $service->voidLatestInstallment($client, $admin);

        $this->assertSame(4, $result['restored_count']);
        $this->assertSame(4, LocalClientPaymentEvent::query()->where('note', 'Installment voided')->count());
        $this->assertSame(0, LocalClientInstallment::query()->count());

        foreach ($campaigns as $campaign) {
            $campaign->refresh();
            $this->assertSame('unpaid', $campaign->billing_payment_status);
            $this->assertNull($campaign->billing_amount_paid);
        }
    }

    public function test_void_refuses_an_installment_that_is_no_longer_latest(): void
    {
        $client = $this->client();
        $this->campaigns($client, 4);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 200, $admin);
        $older = LocalClientInstallment::query()->first();
        $service->recordInstallment($client, 200, $admin);

        try {
            $service->voidLatestInstallment($client, $admin, $older->id);
            $this->fail('Expected the older installment to be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This installment is no longer the latest and was not voided.',
                $exception->errors()['installment'][0],
            );
        }

        $this->assertSame(2, LocalClientInstallment::query()->count());
    }

    public function test_a_used_installment_token_does_not_record_a_second_payment(): void
    {
        $client = $this->client();
        $this->campaigns($client, 2);
        Auth::guard('admin')->setUser($this->admin());

        $token = 'installment-token-once-1234567890';
        $key = 'installment_form_token.'.$client->id;
        session([$key => $token]);

        $controller = app(LocalClientController::class);
        $service = app(LocalClientPaymentService::class);

        $invalid = Request::create('/', 'POST', [
            'amount' => '',
            'form_token' => $token,
        ]);

        try {
            $controller->storeInstallment($invalid, $client, $service);
            $this->fail('An empty amount should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame($token, session($key));
        $this->assertSame(0, LocalClientInstallment::query()->count());

        $controller->storeInstallment(Request::create('/', 'POST', [
            'amount' => '50',
            'note' => 'First',
            'form_token' => $token,
        ]), $client, $service);

        $this->assertSame(1, LocalClientInstallment::query()->count());
        $this->assertNull(session($key));

        try {
            $controller->storeInstallment(Request::create('/', 'POST', [
                'amount' => '50',
                'form_token' => $token,
            ]), $client, $service);
            $this->fail('The used token should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This installment was already recorded.',
                $exception->errors()['amount'][0],
            );
        }

        $this->assertSame(1, LocalClientInstallment::query()->count());
    }

    public function test_ledger_filter_keeps_the_selected_client_and_date(): void
    {
        $client = $this->client();
        $other = LocalClient::query()->create([
            'name' => 'Client B',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('b', 64),
            'created_by_admin_id' => 1,
        ]);

        LocalClientInstallment::query()->create([
            'local_client_id' => $client->id,
            'amount' => 50,
            'currency' => 'USD',
            'source' => 'payment',
            'note' => 'Kept',
            'admin_id' => 1,
            'created_at' => Carbon::parse('2026-09-02 10:00:00'),
        ]);
        LocalClientInstallment::query()->create([
            'local_client_id' => $other->id,
            'amount' => 80,
            'currency' => 'USD',
            'source' => 'payment',
            'admin_id' => 1,
            'created_at' => Carbon::parse('2026-09-02 11:00:00'),
        ]);
        LocalClientInstallment::query()->create([
            'local_client_id' => $client->id,
            'amount' => 20,
            'currency' => 'USD',
            'source' => 'reallocation',
            'admin_id' => 1,
            'created_at' => Carbon::parse('2026-08-01 10:00:00'),
        ]);

        $rows = LocalClientInstallment::query()
            ->forLedger([
                'client_id' => $client->id,
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ])
            ->orderByDesc('id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Kept', $rows->first()->note);
        $this->assertSame('Payment', $rows->first()->sourceLabel());
        $this->assertSame('', $rows->first()->allocationSummary());
    }

    public function test_account_summary_counts_payments_and_this_months_bill(): void
    {
        Carbon::setTestNow('2026-10-07 12:00:00');
        $client = $this->client();

        LocalClientInstallment::query()->create([
            'local_client_id' => $client->id,
            'amount' => 50,
            'currency' => 'USD',
            'source' => 'payment',
            'admin_id' => 1,
            'created_at' => Carbon::parse('2026-09-02 10:00:00'),
        ]);
        LocalClientInstallment::query()->create([
            'local_client_id' => $client->id,
            'amount' => 30,
            'currency' => 'USD',
            'source' => 'payment',
            'admin_id' => 1,
            'created_at' => Carbon::parse('2026-10-03 10:00:00'),
        ]);
        LocalClientInstallment::query()->create([
            'local_client_id' => $client->id,
            'amount' => 20,
            'currency' => 'USD',
            'source' => 'reallocation',
            'admin_id' => 1,
            'created_at' => Carbon::parse('2026-10-04 10:00:00'),
        ]);

        $summary = app(LocalClientPaymentService::class)->installmentAccountSummary($client, [
            [
                'created_at' => '2026-10-02 09:00:00',
                'billing_total' => '100.00',
                'billing_currency' => 'USD',
            ],
            [
                'created_at' => '2026-09-02 09:00:00',
                'billing_total' => '40.00',
                'billing_currency' => 'USD',
            ],
        ]);

        $this->assertSame('$0.00', $summary['still_to_pay']);
        $this->assertSame('$80.00', $summary['received']);
        $this->assertSame('$30.00', $summary['month_received']);
        $this->assertSame('$100.00', $summary['month_billed']);
        $this->assertSame('October 2026', $summary['month_label']);
    }

    public function test_void_is_refused_after_the_campaign_total_changes(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 1);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 50, $admin);
        $campaigns[0]->forceFill(['billing_total' => 80])->save();

        $this->expectException(ValidationException::class);
        $service->voidLatestInstallment($client, $admin);
    }

    public function test_deleted_older_bill_is_paid_before_a_newer_live_campaign(): void
    {
        $client = $this->client();
        $live = $this->makeCampaign($client, 'live', '2026-08-04 09:00:00');

        $archived = LocalClientDeletedCampaignBilling::query()->create([
            'local_client_id' => $client->id,
            'billable_type' => Campaign::class,
            'billable_id' => 99999,
            'campaign_type' => 'campaign',
            'campaign_no' => 'deleted-old',
            'billing_total' => 200,
            'billing_currency' => 'USD',
            'billing_payment_status' => 'unpaid',
            'campaign_created_at' => Carbon::parse('2026-08-01 09:00:00'),
        ]);

        app(LocalClientPaymentService::class)->recordInstallment($client, 200, $this->admin());

        $archived->refresh();
        $live->refresh();

        $this->assertSame('paid', $archived->billing_payment_status);
        $this->assertSame('unpaid', $live->billing_payment_status);
    }

    public function test_archived_bill_is_skipped_when_the_live_campaign_still_exists(): void
    {
        $client = $this->client();
        $live = $this->makeCampaign($client, 'live', '2026-08-04 09:00:00');

        $archived = LocalClientDeletedCampaignBilling::query()->create([
            'local_client_id' => $client->id,
            'billable_type' => Campaign::class,
            'billable_id' => $live->id,
            'campaign_type' => 'campaign',
            'campaign_no' => 'duplicate-archive',
            'billing_total' => 200,
            'billing_currency' => 'USD',
            'billing_payment_status' => 'unpaid',
            'campaign_created_at' => Carbon::parse('2026-08-01 09:00:00'),
        ]);

        app(LocalClientPaymentService::class)->recordInstallment($client, 200, $this->admin());

        $archived->refresh();
        $live->refresh();

        $this->assertSame('unpaid', $archived->billing_payment_status);
        $this->assertSame('paid', $live->billing_payment_status);
    }

    public function test_same_timestamp_uses_billable_type_then_id(): void
    {
        $client = $this->client();
        $createdAt = '2026-08-03 10:00:00';
        $post = $this->makeCampaign($client, 'post', $createdAt);
        $sidebar = SidebarCampaign::query()->create([
            'campaign_no' => 'sidebar-1',
            'report_token' => str_repeat('s', 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => 200,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => '200.00', 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $sidebar->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();

        app(LocalClientPaymentService::class)->recordInstallment($client, 200, $this->admin());

        $post->refresh();
        $sidebar->refresh();

        $this->assertSame('paid', $post->billing_payment_status);
        $this->assertSame('unpaid', $sidebar->billing_payment_status);
    }

    public function test_surplus_after_a_price_drop_moves_to_the_next_open_campaign(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 2);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 100, $admin);
        $campaigns[0]->forceFill([
            'billing_total' => 50,
            'billing_payment_status' => 'paid',
            'billing_amount_paid' => null,
            'billing_paid_at' => now(),
        ])->save();

        $service->reallocateInstallmentSurplus($campaigns[0]->fresh(), 5000, $admin);

        $campaigns[0]->refresh();
        $campaigns[1]->refresh();

        $this->assertSame('paid', $campaigns[0]->billing_payment_status);
        $this->assertSame(50.0, (float) $campaigns[0]->billing_total);
        $this->assertSame('semi_paid', $campaigns[1]->billing_payment_status);
        $this->assertSame(5000, LocalClientPaymentService::toCents($campaigns[1]->billing_amount_paid));
        $this->assertSame('reallocation', LocalClientInstallment::query()->latest('id')->value('source'));
    }

    public function test_report_paid_total_includes_the_semi_paid_amount(): void
    {
        $summary = app(LocalClientBillingReportService::class)->summarize(collect([
            [
                'billing_total' => 200,
                'billing_payment_status' => 'paid',
                'billing_amount_paid' => null,
                'billing_balance_due' => '0.00',
            ],
            [
                'billing_total' => 200,
                'billing_payment_status' => 'semi_paid',
                'billing_amount_paid' => 100,
                'billing_balance_due' => '100.00',
            ],
            [
                'billing_total' => 200,
                'billing_payment_status' => 'unpaid',
                'billing_amount_paid' => null,
                'billing_balance_due' => '200.00',
            ],
        ]));

        $this->assertEquals(600.0, $summary['total_billed']);
        $this->assertEquals(300.0, $summary['total_paid']);
        $this->assertEquals(300.0, $summary['total_unpaid']);
        $this->assertSame(1, $summary['paid_count']);
        $this->assertSame(2, $summary['unpaid_count']);
    }

    public function test_unmarking_one_installment_campaign_moves_that_cash_onto_the_next_unpaid_campaign(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 6);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 800, $admin);
        $installmentId = (int) LocalClientInstallment::query()->value('id');

        $service->markPaidPeriodUnpaid($client, $campaigns[0]->fresh()->billing_paid_at, $admin, [
            'live:campaign:'.$campaigns[0]->id,
        ]);

        $campaigns[0]->refresh();
        $campaigns[3]->refresh();
        $campaigns[4]->refresh();

        $this->assertSame('unpaid', $campaigns[0]->billing_payment_status);
        $this->assertSame('paid', $campaigns[3]->billing_payment_status);
        $this->assertSame('paid', $campaigns[4]->billing_payment_status);
        $this->assertSame('800.00', LocalClientInstallment::query()->find($installmentId)->amount);
        $this->assertSame(1, LocalClientInstallment::query()->count());
        $this->assertSame(0, LocalClientInstallmentAllocation::query()
            ->where('billable_id', $campaigns[0]->id)
            ->count());
        $this->assertSame(80000, LocalClientInstallmentAllocation::query()
            ->where('installment_id', $installmentId)
            ->get()
            ->sum(fn ($row) => LocalClientPaymentService::toCents($row->amount)));
        $this->assertTrue(LocalClientInstallmentAllocation::query()
            ->where('installment_id', $installmentId)
            ->where('billable_id', $campaigns[4]->id)
            ->exists());
    }

    public function test_each_installment_keeps_its_own_allocation_when_one_campaign_is_unmarked(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 3);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 200, $admin);
        $firstId = (int) LocalClientInstallment::query()->value('id');
        $service->recordInstallment($client, 200, $admin);
        $secondId = (int) LocalClientInstallment::query()->where('id', '!=', $firstId)->value('id');

        $service->toggle($campaigns[0]->fresh(), 'unpaid', $admin);

        $campaigns[0]->refresh();
        $campaigns[1]->refresh();
        $campaigns[2]->refresh();

        $this->assertSame('unpaid', $campaigns[0]->billing_payment_status);
        $this->assertSame('paid', $campaigns[1]->billing_payment_status);
        $this->assertSame('paid', $campaigns[2]->billing_payment_status);
        $this->assertTrue(LocalClientInstallmentAllocation::query()
            ->where('installment_id', $firstId)
            ->where('billable_id', $campaigns[2]->id)
            ->exists());
        $this->assertTrue(LocalClientInstallmentAllocation::query()
            ->where('installment_id', $secondId)
            ->where('billable_id', $campaigns[1]->id)
            ->exists());
    }

    public function test_unmarking_stops_when_installment_cash_has_nowhere_to_move(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 2);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->recordInstallment($client, 400, $admin);

        try {
            $service->markPaidPeriodUnpaid($client, $campaigns[0]->fresh()->billing_paid_at, $admin, [
                'live:campaign:'.$campaigns[0]->id,
                'live:campaign:'.$campaigns[1]->id,
            ]);
            $this->fail('Installment cash with no open campaign should be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('no other unpaid campaign', $exception->errors()['billing_payment_status'][0]);
        }

        foreach ($campaigns as $campaign) {
            $campaign->refresh();
            $this->assertSame('paid', $campaign->billing_payment_status);
        }
    }

    public function test_only_the_selected_campaign_in_a_paid_period_is_unmarked(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 2);
        $service = app(LocalClientPaymentService::class);
        $admin = $this->admin();

        $service->markOpenPeriodPaid($client, $admin);
        $this->assertSame(1, LocalClientBillingPeriod::query()->count());

        $service->markPaidPeriodUnpaid($client, $campaigns[0]->fresh()->billing_paid_at, $admin, [
            'live:campaign:'.$campaigns[0]->id,
        ]);

        $campaigns[0]->refresh();
        $campaigns[1]->refresh();

        $this->assertSame('unpaid', $campaigns[0]->billing_payment_status);
        $this->assertSame('paid', $campaigns[1]->billing_payment_status);
        $this->assertSame(0, LocalClientBillingPeriod::query()->count());
    }

    public function test_moved_installment_cash_can_leave_the_next_campaign_semi_paid(): void
    {
        $client = $this->client();
        $first = $this->makeCampaign($client, 'semi-source', '2026-08-01 09:00:00', 200);
        $second = $this->makeCampaign($client, 'semi-target', '2026-08-02 09:00:00', 500);
        $service = app(LocalClientPaymentService::class);

        $service->recordInstallment($client, 200, $this->admin());
        $service->toggle($first->fresh(), 'unpaid', $this->admin());

        $first->refresh();
        $second->refresh();

        $this->assertSame('unpaid', $first->billing_payment_status);
        $this->assertSame('semi_paid', $second->billing_payment_status);
        $this->assertNull($second->billing_paid_at);
        $this->assertSame(20000, LocalClientPaymentService::toCents($second->billing_amount_paid));
    }

    public function test_a_regular_admin_cannot_mark_a_paid_campaign_unpaid(): void
    {
        $client = $this->client();
        $campaigns = $this->campaigns($client, 1);
        $service = app(LocalClientPaymentService::class);
        $service->markOpenPeriodPaid($client, $this->admin());

        $owner = new Admin([
            'id' => 1,
            'name' => 'Owner',
            'slug' => 'owner',
            'email' => 'o@test.com',
            'password' => 'x',
            'type' => 2,
        ]);
        $owner->exists = true;

        try {
            $service->toggle($campaigns[0]->fresh(), 'unpaid', $owner);
            $this->fail('A regular admin should not mark a campaign unpaid.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Only a Super Admin can mark a campaign unpaid.',
                $exception->errors()['billing_payment_status'][0],
            );
        }

        $campaigns[0]->refresh();
        $this->assertSame('paid', $campaigns[0]->billing_payment_status);

        $service->toggle($campaigns[0]->fresh(), 'unpaid', $this->admin(), 'Opened again');
        $campaigns[0]->refresh();
        $this->assertSame('unpaid', $campaigns[0]->billing_payment_status);
        $this->assertSame('Opened again', $campaigns[0]->billing_payment_note);

        $campaigns[0]->forceFill([
            'billing_payment_status' => 'unpaid',
            'billing_paid_at' => null,
        ])->save();

        $service->toggle($campaigns[0]->fresh(), 'unpaid', $owner, 'Still open');

        $campaigns[0]->refresh();
        $this->assertSame('unpaid', $campaigns[0]->billing_payment_status);
        $this->assertSame('Still open', $campaigns[0]->billing_payment_note);
    }

    private function client(): LocalClient
    {
        return LocalClient::query()->create([
            'name' => 'Client A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('a', 64),
            'created_by_admin_id' => 1,
        ]);
    }

    private function admin(): Admin
    {
        $admin = new Admin([
            'id' => 1,
            'name' => 'Super',
            'slug' => 'super',
            'email' => 's@test.com',
            'password' => 'x',
            'type' => 0,
        ]);
        $admin->exists = true;

        return $admin;
    }

    /**
     * @return list<Campaign>
     */
    private function campaigns(LocalClient $client, int $count): array
    {
        $campaigns = [];

        for ($index = 0; $index < $count; $index++) {
            $campaigns[] = $this->makeCampaign(
                $client,
                'cmp-'.$index,
                Carbon::parse('2026-08-01 09:00:00')->addDays($index)->toDateTimeString(),
            );
        }

        return $campaigns;
    }

    private function makeCampaign(
        LocalClient $client,
        string $number,
        string $createdAt,
        float $total = 200,
        string $currency = 'USD',
    ): Campaign {
        $campaign = Campaign::query()->create([
            'campaign_no' => $number,
            'report_token' => str_pad($number, 64, 'x'),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => $total,
            'billing_currency' => $currency,
            'billing_snapshot' => ['total' => number_format($total, 2, '.', ''), 'lines' => []],
            'billing_payment_status' => 'unpaid',
        ]);
        $campaign->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();

        return $campaign->fresh();
    }
}
