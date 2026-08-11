<?php

namespace Tests\Unit;

use App\Models\Admin\BillingCampaignType;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillLine;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Services\LocalClientBillingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalClientBillingServiceTest extends TestCase
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
            $table->tinyInteger('type')->default(1);
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
            $table->unsignedBigInteger('domain_category_id');
            $table->unsignedBigInteger('admin_id')->default(1);
            $table->integer('status')->default(1);
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

        Schema::table('domain_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('domain_categories', 'id')) {
                return;
            }
        });

        \DB::table('admins')->insert([
            'id' => 1,
            'name' => 'Super',
            'slug' => 'super',
            'email' => 'super@test.com',
            'password' => bcrypt('secret'),
            'type' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('domain_categories')->insert([
            ['id' => 1, 'name' => 'Old .com', 'slug' => 'old-com', 'admin_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => '.co.uk', 'slug' => 'co-uk', 'admin_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('local_client_bill_lines');
        Schema::dropIfExists('local_client_domain_category_prices');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('domain_categories');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_calculates_mixed_category_totals(): void
    {
        $client = LocalClient::create([
            'name' => 'Acme',
            'default_currency' => 'EUR',
            'billing_report_token' => str_repeat('a', 64),
            'created_by_admin_id' => 1,
        ]);

        LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 1,
            'post_price' => 11,
        ]);
        LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 2,
            'post_price' => 7,
        ]);

        $d1 = Domain::create(['name' => 'a.com', 'domain_category_id' => 2, 'admin_id' => 1]);
        $d2 = Domain::create(['name' => 'b.com', 'domain_category_id' => 2, 'admin_id' => 1]);
        $d3 = Domain::create(['name' => 'c.com', 'domain_category_id' => 1, 'admin_id' => 1]);

        $service = app(LocalClientBillingService::class);
        $result = $service->calculate($client, BillingCampaignType::Post, [$d1->id, $d2->id, $d3->id]);

        $this->assertSame('25.00', $result->total);
        $this->assertCount(3, $result->lines);
    }

    public function test_rejects_missing_price_for_category(): void
    {
        $client = LocalClient::create([
            'name' => 'Acme',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('b', 64),
            'created_by_admin_id' => 1,
        ]);

        $domain = Domain::create(['name' => 'x.com', 'domain_category_id' => 1, 'admin_id' => 1]);

        $this->expectException(ValidationException::class);

        app(LocalClientBillingService::class)->calculate($client, BillingCampaignType::Post, [$domain->id]);
    }

    public function test_apply_to_campaign_creates_ledger_and_unpaid_status(): void
    {
        $client = LocalClient::create([
            'name' => 'Acme',
            'default_currency' => 'IDR',
            'billing_report_token' => str_repeat('c', 64),
            'created_by_admin_id' => 1,
        ]);

        LocalClientDomainCategoryPrice::create([
            'local_client_id' => $client->id,
            'domain_category_id' => 1,
            'post_price' => 10,
        ]);

        $domain = Domain::create(['name' => 'x.com', 'domain_category_id' => 1, 'admin_id' => 1]);
        $campaign = Campaign::create([
            'campaign_no' => 'CMP-TEST',
            'report_token' => str_repeat('d', 64),
            'admin_id' => 1,
        ]);

        $service = app(LocalClientBillingService::class);
        $result = $service->calculate($client, BillingCampaignType::Post, [$domain->id]);
        $service->applyToCampaign($campaign, $result, $client, 'IDR');

        $campaign->refresh();

        $this->assertSame('unpaid', $campaign->billing_payment_status);
        $this->assertSame('10.00', (string) $campaign->billing_total);
        $this->assertSame('IDR', $campaign->billing_currency);
        $this->assertSame(1, LocalClientBillLine::count());
    }
}
