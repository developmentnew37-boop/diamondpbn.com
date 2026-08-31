<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\LocalClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalClientBillingReportStatusFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

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

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->string('report_token', 64);
            $table->unsignedBigInteger('admin_id');
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

        Admin::unguard();
        Admin::create(['id' => 1, 'name' => 'Super', 'slug' => 'super', 'email' => 's@test.com', 'password' => 'x', 'type' => 0]);
        Admin::reguard();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('local_client_domain_category_prices');
        Schema::dropIfExists('domain_categories');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_public_report_defaults_to_current_month_unpaid(): void
    {
        $client = $this->makeClient();

        $this->makeCampaign($client, 'cmp-unpaid-aug', 40, 'unpaid', '2026-08-10 10:00:00');
        $this->makeCampaign($client, 'cmp-paid-aug', 60, 'paid', '2026-08-12 10:00:00');
        $this->makeCampaign($client, 'cmp-unpaid-july', 25, 'unpaid', '2026-07-05 10:00:00');

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token)
            ->assertOk()
            ->assertSee('cmp-unpaid-aug', false)
            ->assertDontSee('cmp-paid-aug', false)
            ->assertDontSee('cmp-unpaid-july', false)
            ->assertSee('Overall outstanding', false)
            ->assertSee('This month outstanding', false)
            ->assertSee('August 2026', false)
            ->assertSee('billing-status-quick-filters', false);
    }

    public function test_overall_outstanding_includes_older_months_when_current_month_clear(): void
    {
        $client = $this->makeClient();

        $this->makeCampaign($client, 'cmp-paid-aug', 60, 'paid', '2026-08-12 10:00:00');
        $this->makeCampaign($client, 'cmp-unpaid-july', 25, 'unpaid', '2026-07-05 10:00:00');

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token)
            ->assertOk()
            ->assertSee('Overall outstanding', false)
            ->assertSee('$25.00', false)
            ->assertSee('This month outstanding', false)
            ->assertSee('billing-outstanding-breakdown-btn', false)
            ->assertSee('remaining in July 2026', false);
    }

    public function test_may_filter_shows_may_unpaid_campaign_on_day_31(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00'));

        $client = $this->makeClient();

        $this->makeCampaign($client, 'cmp-unpaid-may', 180, 'unpaid', '2026-05-15 10:00:00');

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token.'?filter_year=2026&filter_month=05&status=unpaid')
            ->assertOk()
            ->assertSee('cmp-unpaid-may', false)
            ->assertSee('May 2026', false);

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token.'?filter_year=2026&filter_month=06&status=unpaid')
            ->assertOk()
            ->assertDontSee('cmp-unpaid-may', false);
    }

    public function test_other_month_filter_shows_that_month_only(): void
    {
        $client = $this->makeClient();

        $this->makeCampaign($client, 'cmp-unpaid-aug', 40, 'unpaid', '2026-08-10 10:00:00');
        $this->makeCampaign($client, 'cmp-unpaid-july', 25, 'unpaid', '2026-07-05 10:00:00');

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token.'?filter_year=2026&filter_month=7&status=unpaid')
            ->assertOk()
            ->assertSee('cmp-unpaid-july', false)
            ->assertDontSee('cmp-unpaid-aug', false)
            ->assertSee('July 2026', false)
            ->assertSee('This month outstanding', false);
    }

    public function test_all_status_shows_three_summary_boxes(): void
    {
        $client = $this->makeClient();

        $this->makeCampaign($client, 'cmp-unpaid-row', 40, 'unpaid', '2026-08-10 10:00:00');
        $this->makeCampaign($client, 'cmp-paid-row', 60, 'paid', '2026-08-12 10:00:00');

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token.'?status=all')
            ->assertOk()
            ->assertSee('cmp-unpaid-row', false)
            ->assertSee('cmp-paid-row', false)
            ->assertSee('Total billed', false)
            ->assertSee('Outstanding', false)
            ->assertSee('Payment collection', false);
    }

    public function test_paid_status_shows_paid_box_only(): void
    {
        $client = $this->makeClient();

        $this->makeCampaign($client, 'cmp-unpaid-row', 40, 'unpaid', '2026-08-10 10:00:00');
        $this->makeCampaign($client, 'cmp-paid-row', 60, 'paid', '2026-08-12 10:00:00');

        $this->get('/client/billing/'.$client->id.'/'.$client->billing_report_token.'?status=paid')
            ->assertOk()
            ->assertSee('cmp-paid-row', false)
            ->assertDontSee('cmp-unpaid-row', false)
            ->assertDontSee('Total billed', false)
            ->assertDontSee('Outstanding unpaid', false)
            ->assertDontSee('This month outstanding', false)
            ->assertSee('>Paid</div>', false);
    }

    private function makeClient(): LocalClient
    {
        return LocalClient::create([
            'name' => 'Status Filter Client',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('f', 64),
            'created_by_admin_id' => 1,
        ]);
    }

    private function makeCampaign(
        LocalClient $client,
        string $campaignNo,
        float $total,
        string $status,
        string $createdAt,
    ): Campaign {
        $campaign = Campaign::create([
            'campaign_no' => $campaignNo,
            'report_token' => str_repeat(substr(md5($campaignNo), 0, 1), 64),
            'admin_id' => 1,
            'local_client_id' => $client->id,
            'billing_total' => $total,
            'billing_currency' => 'USD',
            'billing_snapshot' => ['total' => number_format($total, 2, '.', ''), 'lines' => []],
            'billing_payment_status' => $status,
            'billing_paid_at' => $status === 'paid' ? $createdAt : null,
        ]);

        Campaign::whereKey($campaign->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);

        return $campaign->fresh();
    }
}
