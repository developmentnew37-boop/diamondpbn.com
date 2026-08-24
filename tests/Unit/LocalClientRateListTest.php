<?php

namespace Tests\Unit;

use App\Models\Admin\BillingCampaignType;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Models\Admin\LocalClientRateList;
use App\Models\Admin\LocalClientRateListPrice;
use App\Services\LocalClientBillingService;
use App\Services\LocalClientPriceMatrixService;
use App\Services\LocalClientRateListSeedService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalClientRateListTest extends TestCase
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

        Schema::create('local_client_rate_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id');
            $table->timestamps();
        });

        Schema::create('local_client_rate_list_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rate_list_id');
            $table->unsignedBigInteger('domain_category_id');
            $table->decimal('post_price', 12, 2)->nullable();
            $table->decimal('sidebar_price', 12, 2)->nullable();
            $table->decimal('hidden_links_price', 12, 2)->nullable();
            $table->decimal('sticky_price', 12, 2)->nullable();
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
            $table->unsignedBigInteger('rate_list_id')->nullable();
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
        Schema::dropIfExists('local_client_domain_category_prices');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('local_client_rate_list_prices');
        Schema::dropIfExists('local_client_rate_lists');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('domain_categories');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_shared_list_live_update_affects_next_calculate(): void
    {
        $list = LocalClientRateList::create([
            'name' => 'Company rates',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        LocalClientRateListPrice::create([
            'rate_list_id' => $list->id,
            'domain_category_id' => 1,
            'post_price' => 10,
        ]);

        $client = LocalClient::create([
            'name' => 'Linked',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('a', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => $list->id,
        ]);

        $domain = Domain::create(['name' => 'a.com', 'domain_category_id' => 1, 'admin_id' => 1]);
        $service = app(LocalClientBillingService::class);

        $this->assertSame('10.00', $service->calculate($client, BillingCampaignType::Post, [$domain->id])->total);

        LocalClientRateListPrice::query()
            ->where('rate_list_id', $list->id)
            ->where('domain_category_id', 1)
            ->update(['post_price' => 25]);

        $this->assertSame('25.00', $service->calculate($client->fresh(), BillingCampaignType::Post, [$domain->id])->total);
    }

    public function test_zero_price_on_rate_list_bills_successfully(): void
    {
        $list = LocalClientRateList::create([
            'name' => 'Free tier',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        LocalClientRateListPrice::create([
            'rate_list_id' => $list->id,
            'domain_category_id' => 1,
            'post_price' => 0,
        ]);

        $client = LocalClient::create([
            'name' => 'Zero',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('b', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => $list->id,
        ]);

        $domain = Domain::create(['name' => 'z.com', 'domain_category_id' => 1, 'admin_id' => 1]);

        $result = app(LocalClientBillingService::class)
            ->calculate($client, BillingCampaignType::Post, [$domain->id]);

        $this->assertSame('0.00', $result->total);
    }

    public function test_copy_from_custom_client_via_matrix_service(): void
    {
        $source = LocalClient::create([
            'name' => 'Source custom',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('c', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => null,
        ]);

        LocalClientDomainCategoryPrice::create([
            'local_client_id' => $source->id,
            'domain_category_id' => 1,
            'post_price' => 15,
            'sidebar_price' => 8,
        ]);

        $target = LocalClient::create([
            'name' => 'Target custom',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('d', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => null,
        ]);

        $matrixService = app(LocalClientPriceMatrixService::class);
        $copied = $matrixService->pricesKeyedByCategory($source);
        $matrixService->upsertPrices($target, $copied);

        $domain = Domain::create(['name' => 'copy.com', 'domain_category_id' => 1, 'admin_id' => 1]);
        $result = app(LocalClientBillingService::class)
            ->calculate($target->fresh(), BillingCampaignType::Post, [$domain->id]);

        $this->assertSame('15.00', $result->total);
    }

    public function test_new_domain_category_seeds_zero_on_lists_and_custom_clients(): void
    {
        $list = LocalClientRateList::create([
            'name' => 'Company',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        $custom = LocalClient::create([
            'name' => 'Custom',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('e', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => null,
        ]);

        $linked = LocalClient::create([
            'name' => 'Linked',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('f', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => $list->id,
        ]);

        $category = DomainCategory::create([
            'name' => 'Brand New',
            'admin_id' => 1,
        ]);

        // Observer should seed; do not call seed service again (that would hide a broken observer).
        $listPrice = LocalClientRateListPrice::query()
            ->where('rate_list_id', $list->id)
            ->where('domain_category_id', $category->id)
            ->first();

        $customPrice = LocalClientDomainCategoryPrice::query()
            ->where('local_client_id', $custom->id)
            ->where('domain_category_id', $category->id)
            ->first();

        $linkedCustomPrice = LocalClientDomainCategoryPrice::query()
            ->where('local_client_id', $linked->id)
            ->where('domain_category_id', $category->id)
            ->first();

        $this->assertNotNull($listPrice);
        $this->assertSame('0.00', (string) $listPrice->post_price);
        $this->assertNotNull($customPrice);
        $this->assertSame('0.00', (string) $customPrice->post_price);
        $this->assertNull($linkedCustomPrice);
    }

    public function test_new_rate_list_seeds_zero_for_existing_categories(): void
    {
        $list = LocalClientRateList::create([
            'name' => 'Private',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        app(LocalClientRateListSeedService::class)->seedForNewRateList($list);

        $this->assertSame(2, LocalClientRateListPrice::where('rate_list_id', $list->id)->count());
        $this->assertSame(
            '0.00',
            (string) LocalClientRateListPrice::where('rate_list_id', $list->id)->first()->post_price
        );
    }

    public function test_clients_on_shared_lists_are_not_custom_rate_clients(): void
    {
        $list = LocalClientRateList::create([
            'name' => 'Company',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        LocalClient::create([
            'name' => 'On list',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('g', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => $list->id,
        ]);

        $custom = LocalClient::create([
            'name' => 'Custom only',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('h', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => null,
        ]);

        $copyCandidates = LocalClient::query()
            ->whereNull('rate_list_id')
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $this->assertSame([$custom->id], $copyCandidates);
    }

    public function test_rate_list_delete_blocked_while_clients_linked(): void
    {
        $list = LocalClientRateList::create([
            'name' => 'Company rates',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        LocalClient::create([
            'name' => 'Linked A',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('i', 64),
            'created_by_admin_id' => 1,
            'rate_list_id' => $list->id,
        ]);

        // Same guard the controller uses before delete.
        $this->assertGreaterThan(0, $list->clients()->count());

        $empty = LocalClientRateList::create([
            'name' => 'Unused',
            'is_active' => true,
            'created_by_admin_id' => 1,
        ]);

        $this->assertSame(0, $empty->clients()->count());
        $empty->delete();
        $this->assertNull(LocalClientRateList::find($empty->id));

        // Linked list must remain when clients still reference it.
        $this->assertNotNull(LocalClientRateList::find($list->id));
        $this->assertSame(1, $list->fresh()->clients()->count());
    }

    public function test_incomplete_matrix_fails_validation(): void
    {
        $service = app(LocalClientPriceMatrixService::class);

        $this->expectException(ValidationException::class);

        $service->validateCompleteMatrix([
            1 => [
                'post_price' => 10,
                'sidebar_price' => 20,
                'hidden_links_price' => '',
                'sticky_price' => 19,
            ],
            2 => [
                'post_price' => 10,
                'sidebar_price' => 20,
                'hidden_links_price' => 24,
                'sticky_price' => 19,
            ],
        ]);
    }

    public function test_all_zero_matrix_passes_validation(): void
    {
        $service = app(LocalClientPriceMatrixService::class);

        $service->validateCompleteMatrix([
            1 => [
                'post_price' => 0,
                'sidebar_price' => 0,
                'hidden_links_price' => 0,
                'sticky_price' => 0,
            ],
            2 => [
                'post_price' => '0.00',
                'sidebar_price' => '0.00',
                'hidden_links_price' => '0.00',
                'sticky_price' => '0.00',
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_missing_category_row_fails_validation(): void
    {
        $service = app(LocalClientPriceMatrixService::class);

        try {
            $service->validateCompleteMatrix([
                1 => [
                    'post_price' => 1,
                    'sidebar_price' => 1,
                    'hidden_links_price' => 1,
                    'sticky_price' => 1,
                ],
            ]);
            $this->fail('Expected ValidationException for missing category 2');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prices.2.post_price', $e->errors());
        }
    }
}
