<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Models\Admin\LocalClientManualBilling;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Services\LocalClientBillingReportService;
use App\Services\LocalClientDeletionService;
use App\Services\LocalClientManualBillingService;
use App\Services\LocalClientPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalClientManualBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('domain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
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
            $table->string('default_currency', 3)->default('USD');
            $table->string('billing_report_token', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->default(1);
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

        Schema::create('local_client_manual_billings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_client_id');
            $table->string('campaign_no', 191);
            $table->string('report_token', 64)->nullable();
            $table->string('billing_campaign_type', 32);
            $table->text('post_links')->nullable();
            $table->string('keyword', 500)->nullable();
            $table->string('target_url', 2000)->nullable();
            $table->decimal('billing_total', 12, 2);
            $table->decimal('billing_amount_paid', 12, 2)->nullable();
            $table->string('billing_currency', 3);
            $table->json('billing_snapshot');
            $table->string('billing_payment_status', 32)->default('unpaid');
            $table->timestamp('billing_paid_at')->nullable();
            $table->unsignedBigInteger('billing_paid_by_admin_id')->nullable();
            $table->text('billing_payment_note')->nullable();
            $table->unsignedBigInteger('admin_id');
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('local_client_installment_allocations');
        Schema::dropIfExists('local_client_installments');
        Schema::dropIfExists('local_client_payment_events');
        Schema::dropIfExists('local_client_manual_billings');
        Schema::dropIfExists('local_client_domain_category_prices');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('domain_categories');

        parent::tearDown();
    }

    public function test_unpaid_manual_bill_is_priced_labeled_and_included_in_installments(): void
    {
        $client = $this->client();
        $domain = $this->domain('alpha.example', 25);
        $admin = $this->admin();

        $bill = app(LocalClientManualBillingService::class)->create($client, $admin, [
            'campaign_no' => 'Hand built post',
            'campaign_type' => 'post',
            'payment_status' => 'unpaid',
            'post_links' => "https://alpha.example/post\n",
            'keyword' => 'alpha',
            'url' => 'https://client.example',
        ]);

        $this->assertSame('25.00', $bill->billing_total);
        $this->assertSame('unpaid', $bill->billing_payment_status);
        $this->assertNull($bill->billing_paid_at);
        $this->assertSame(0, LocalClientPaymentEvent::query()->count());

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);
        $this->assertCount(1, $rows);
        $this->assertSame('manual_post', $rows[0]['type']);
        $this->assertSame('Manual campaign', $rows[0]['type_label']);
        $this->assertSame('Hand built post', $rows[0]['campaign_no']);
        $this->assertArrayNotHasKey('manual_details', $rows[0]);
        $this->assertSame(64, strlen((string) $bill->report_token));
        $this->assertSame('Post', $bill->reportDetails()['kind']);
        $this->assertSame(['alpha.example'], $bill->reportDetails()['domains']);
        $this->assertSame(['https://alpha.example/post'], $bill->reportDetails()['post_links']);
        $this->assertSame(['alpha'], $bill->reportDetails()['keywords']);
        $this->assertSame(['https://client.example'], $bill->reportDetails()['urls']);
        $this->assertSame(route('admin.local-client.manual-billing.report', [
            'manualBilling' => $bill->id,
            'token' => $bill->report_token,
        ]), $rows[0]['report_url']);
        $this->assertStringNotContainsString($client->billing_report_token, (string) $rows[0]['report_url']);

        $filtered = app(LocalClientBillingReportService::class)->filterCampaigns($rows, [
            'campaign_type' => 'post',
        ]);
        $this->assertCount(1, $filtered);

        $open = app(LocalClientPaymentService::class)->openBalanceSummary($client);
        $this->assertSame('25.00', $open['open_balance']);

        app(LocalClientPaymentService::class)->recordInstallment($client, 10, $admin);
        $bill->refresh();
        $this->assertSame('semi_paid', $bill->billing_payment_status);
        $this->assertSame(1000, LocalClientPaymentService::toCents($bill->billing_amount_paid));
    }

    public function test_paid_manual_bill_is_excluded_from_the_open_balance(): void
    {
        $client = $this->client();
        $domain = $this->domain('beta.example', 40, 'sticky_price');
        $name = str_repeat('N', 80);

        $bill = app(LocalClientManualBillingService::class)->create($client, $this->admin(), [
            'campaign_no' => $name,
            'campaign_type' => 'sticky',
            'payment_status' => 'paid',
            ...$this->entry('beta.example'),
        ]);

        $this->assertSame('paid', $bill->billing_payment_status);
        $this->assertNull($bill->billing_amount_paid);
        $this->assertNotNull($bill->billing_paid_at);
        $this->assertSame(80, mb_strlen($bill->campaign_no));

        $event = LocalClientPaymentEvent::query()->first();
        $this->assertNotNull($event);
        $this->assertSame(64, mb_strlen((string) $event->campaign_no));

        $open = app(LocalClientPaymentService::class)->openBalanceSummary($client);
        $this->assertSame('0.00', $open['open_balance']);

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);
        $postRows = app(LocalClientBillingReportService::class)->filterCampaigns($rows, [
            'campaign_type' => 'post',
        ]);
        $this->assertCount(0, $postRows);
        $this->assertSame('manual_sticky', $rows[0]['type']);
    }

    public function test_sidebar_manual_bill_keeps_the_entered_domains(): void
    {
        $client = $this->client();
        $this->domain('side.example', 15, 'sidebar_price');

        $bill = app(LocalClientManualBillingService::class)->create($client, $this->admin(), [
            'campaign_no' => 'Sidebar by hand',
            'campaign_type' => 'sidebar',
            'payment_status' => 'unpaid',
            'post_links' => "side.example\n",
            'keyword' => 'alpha',
            'url' => 'https://client.example',
        ]);

        $this->assertSame('side.example', $bill->post_links);
        $this->assertSame('15.00', $bill->billing_total);
        $this->assertSame('manual_sidebar', $bill->reportTypeKey());
        $this->assertSame(['side.example'], $bill->reportDetails()['post_links']);
        $this->assertSame(['side.example'], $bill->reportDetails()['domains']);
        $this->assertSame('Entered domains', $bill->reportDetails()['live_label']);
    }

    public function test_missing_rate_and_zero_total_are_rejected(): void
    {
        $client = $this->client();
        $unpriced = Domain::query()->create([
            'name' => 'missing.example',
            'domain_category_id' => 9,
            'admin_id' => 1,
        ]);
        $free = $this->domain('free.example', 0);

        $service = app(LocalClientManualBillingService::class);

        try {
            $service->create($client, $this->admin(), [
                'campaign_no' => 'Missing rate',
                'campaign_type' => 'post',
                'payment_status' => 'unpaid',
                ...$this->entry('missing.example'),
            ]);
            $this->fail('A missing category rate should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('post_links', $exception->errors());
        }

        try {
            $service->create($client, $this->admin(), [
                'campaign_no' => 'Zero bill',
                'campaign_type' => 'post',
                'payment_status' => 'unpaid',
                ...$this->entry('free.example'),
            ]);
            $this->fail('A zero total should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('post_links', $exception->errors());
        }

        $this->assertSame(0, LocalClientManualBilling::query()->count());
    }

    public function test_deleting_a_client_removes_manual_bills(): void
    {
        $client = $this->client();
        $domain = $this->domain('gone.example', 10, 'hidden_links_price');

        app(LocalClientManualBillingService::class)->create($client, $this->admin(), [
            'campaign_no' => 'Temporary',
            'campaign_type' => 'hidden_links',
            'payment_status' => 'unpaid',
            ...$this->entry('gone.example'),
        ]);

        app(LocalClientDeletionService::class)->delete($client);

        $this->assertSame(0, LocalClientManualBilling::query()->count());
        $this->assertSame(0, LocalClient::query()->count());
    }

    public function test_quantity_must_match_domains_keywords_and_urls(): void
    {
        $client = $this->client();
        $first = $this->domain('one.example', 10);
        $second = Domain::query()->create([
            'name' => 'two.example',
            'domain_category_id' => 4,
            'admin_id' => 1,
        ]);
        LocalClientDomainCategoryPrice::query()->create([
            'local_client_id' => $client->id,
            'domain_category_id' => 4,
            'post_price' => 12,
        ]);

        try {
            app(LocalClientManualBillingService::class)->create($client, $this->admin(), [
                'campaign_no' => 'Short',
                'campaign_type' => 'post',
                'payment_status' => 'unpaid',
                'post_links' => 'https://one.example/a',
                'keyword' => "alpha\nbeta",
                'url' => "https://client.example/1\nhttps://client.example/2",
            ]);
            $this->fail('Keyword and URL counts must match the live lines.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('keyword', $exception->errors());
        }

        $bill = app(LocalClientManualBillingService::class)->create($client, $this->admin(), [
            'campaign_no' => 'Matched',
            'campaign_type' => 'post',
            'payment_status' => 'unpaid',
            'post_links' => "https://one.example/a\n\nhttps://two.example/b",
            'keyword' => "same\n\nsame",
            'url' => "https://client.example/1\nhttps://client.example/2",
        ]);

        $this->assertSame('22.00', $bill->billing_total);
        $this->assertSame(['same', 'same'], $bill->reportDetails()['keywords']);
        $this->assertSame([$first->name, $second->name], $bill->reportDetails()['domains']);
    }

    public function test_repeated_lines_are_billed_and_unknown_hosts_are_rejected(): void
    {
        $client = $this->client();
        $this->domain('known.example', 10);
        $service = app(LocalClientManualBillingService::class);

        $preview = $service->preview($client, 'post', "https://known.example/a\nhttps://missing.example/c");
        $this->assertSame(2, $preview['quantity']);
        $this->assertSame(['missing.example'], $preview['missing']);
        $this->assertNull($preview['total']);

        try {
            $service->create($client, $this->admin(), [
                'campaign_no' => 'Missing domain',
                'campaign_type' => 'post',
                'payment_status' => 'unpaid',
                ...$this->entry('not-listed.example'),
            ]);
            $this->fail('A domain that is not in the inventory should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('post_links', $exception->errors());
        }

        try {
            $service->create($client, $this->admin(), [
                'campaign_no' => 'Subdomain',
                'campaign_type' => 'post',
                'payment_status' => 'unpaid',
                'post_links' => 'https://blog.known.example/page',
                'keyword' => 'alpha',
                'url' => 'https://client.example',
            ]);
            $this->fail('A subdomain must not be billed as the root domain.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('blog.known.example', $exception->errors()['post_links'][0]);
        }

        $bill = $service->create($client, $this->admin(), [
            'campaign_no' => 'Repeated domain',
            'campaign_type' => 'post',
            'payment_status' => 'unpaid',
            'post_links' => "https://known.example/a\nhttps://known.example/b",
            'keyword' => "alpha\nbeta",
            'url' => "https://client.example/1\nhttps://client.example/2",
        ]);

        $this->assertSame('20.00', $bill->billing_total);
        $this->assertSame(['known.example', 'known.example'], $bill->reportDetails()['domains']);
        $this->assertSame(1, LocalClientManualBilling::query()->count());
    }

    public function test_www_lookup_prefers_the_exact_inventory_name(): void
    {
        $client = $this->client();
        $this->domain('example.com', 10);
        Domain::query()->create([
            'name' => 'www.example.com',
            'domain_category_id' => 4,
            'admin_id' => 1,
        ]);
        LocalClientDomainCategoryPrice::query()->create([
            'local_client_id' => $client->id,
            'domain_category_id' => 4,
            'post_price' => 12,
        ]);

        $service = app(LocalClientManualBillingService::class);
        $exact = $service->preview($client, 'post', 'https://www.example.com/a-new-era/');
        $bare = $service->preview($client, 'post', 'https://example.com/a-new-era/');

        $this->assertSame('12.00', $exact['total']);
        $this->assertSame('10.00', $bare['total']);
    }

    public function test_www_prefix_matches_the_bare_inventory_domain(): void
    {
        $client = $this->client();
        $this->domain('only-bare.example', 8);

        $preview = app(LocalClientManualBillingService::class)->preview(
            $client,
            'post',
            'https://www.only-bare.example/post',
        );

        $this->assertSame([], $preview['missing']);
        $this->assertSame('8.00', $preview['total']);
        $this->assertSame('only-bare.example', $preview['lines'][0]['domain_name']);
    }

    public function test_schedule_kinds_keep_their_report_type_and_matching_rate(): void
    {
        $client = $this->client();
        Domain::query()->create([
            'name' => 'sched.example',
            'domain_category_id' => 3,
            'admin_id' => 1,
        ]);
        LocalClientDomainCategoryPrice::query()->create([
            'local_client_id' => $client->id,
            'domain_category_id' => 3,
            'post_price' => 10,
            'sidebar_price' => 20,
            'sticky_price' => 30,
        ]);

        $service = app(LocalClientManualBillingService::class);
        $post = $service->create($client, $this->admin(), [
            'campaign_no' => 'Scheduled post',
            'campaign_type' => 'schedule_post',
            'payment_status' => 'unpaid',
            ...$this->entry('sched.example'),
        ]);
        $sidebar = $service->create($client, $this->admin(), [
            'campaign_no' => 'Scheduled blogroll',
            'campaign_type' => 'schedule_sidebar',
            'payment_status' => 'unpaid',
            ...$this->entry('sched.example'),
        ]);
        $sticky = $service->create($client, $this->admin(), [
            'campaign_no' => 'Scheduled sticky',
            'campaign_type' => 'schedule_sticky',
            'payment_status' => 'unpaid',
            ...$this->entry('sched.example'),
        ]);

        $this->assertSame('schedule_post', $post->billing_campaign_type);
        $this->assertSame('10.00', $post->billing_total);
        $this->assertSame('manual_schedule_post', $post->reportTypeKey());
        $this->assertSame('Schedule Post', $post->reportDetails()['kind']);
        $this->assertSame('schedule_sidebar', $sidebar->billing_campaign_type);
        $this->assertSame('20.00', $sidebar->billing_total);
        $this->assertSame('manual_schedule_sidebar', $sidebar->reportTypeKey());
        $this->assertSame('Schedule Blogroll', $sidebar->reportDetails()['kind']);
        $this->assertSame('schedule_sticky', $sticky->billing_campaign_type);
        $this->assertSame('30.00', $sticky->billing_total);
        $this->assertSame('manual_schedule_sticky', $sticky->reportTypeKey());

        $rows = app(LocalClientBillingReportService::class)->campaignsForClient($client);
        $report = app(LocalClientBillingReportService::class);
        $this->assertCount(1, $report->filterCampaigns($rows, ['campaign_type' => 'post']));
        $this->assertCount(1, $report->filterCampaigns($rows, ['campaign_type' => 'sidebar']));
        $this->assertCount(1, $report->filterCampaigns($rows, ['campaign_type' => 'sticky']));
    }

    public function test_live_urls_match_inventory_hosts_and_the_line_cap_is_enforced(): void
    {
        $client = $this->client();
        $this->domain('known.example', 10);
        $service = app(LocalClientManualBillingService::class);

        $preview = $service->preview($client, 'post', implode("\n", [
            'https://known.example?ref=1',
            'https://user:pass@known.example/path#section',
            '//known.example/post.',
        ]));

        $this->assertSame([], $preview['missing']);
        $this->assertSame(3, $preview['quantity']);
        $this->assertSame('30.00', $preview['total']);

        try {
            $service->preview($client, 'post', implode("\n", array_fill(0, 501, 'https://known.example/post')));
            $this->fail('More than 500 live lines should be rejected before a total is shown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('post_links', $exception->errors());
        }

        $this->assertSame(0, LocalClientManualBilling::query()->count());
    }

    public function test_manual_report_page_lists_entered_lines_and_rejects_a_bad_token(): void
    {
        $client = $this->client();
        $this->domain('alpha.example', 25);
        $bill = app(LocalClientManualBillingService::class)->create($client, $this->admin(), [
            'campaign_no' => 'Hand built post',
            'campaign_type' => 'post',
            'payment_status' => 'unpaid',
            'post_links' => "https://alpha.example/post\n",
            'keyword' => 'alpha',
            'url' => 'https://client.example',
        ]);

        $report = $this->get(route('admin.local-client.manual-billing.report', [
            'manualBilling' => $bill->id,
            'token' => $bill->report_token,
        ]));
        $report->assertOk();
        $report->assertSee('Hand built post Campaign Report');
        $report->assertSee('Total Posts');
        $report->assertSee('Export Excel');
        $report->assertSee('alpha.example');
        $report->assertSee('https://alpha.example/post');
        $report->assertSee('alpha');
        $report->assertSee('https://client.example');
        $report->assertSee('Live');

        $this->get(route('admin.local-client.manual-billing.report', [
            'manualBilling' => $bill->id,
            'token' => str_repeat('x', 64),
        ]))->assertNotFound();

        $billing = $this->get(route('admin.local-client.billing.report', [
            'id' => $client->id,
            'token' => $client->billing_report_token,
        ]));
        $billing->assertOk();
        $billing->assertSee('View report');
        $billing->assertSee('/manual/billing/report/'.$bill->id.'/'.$bill->report_token, false);
        $billing->assertDontSee('https://alpha.example/post', false);
    }

    /**
     * @return array{post_links: string, keyword: string, url: string}
     */
    private function entry(string $host): array
    {
        return [
            'post_links' => 'https://'.$host.'/post',
            'keyword' => 'alpha',
            'url' => 'https://client.example',
        ];
    }

    private function client(): LocalClient
    {
        return LocalClient::query()->create([
            'name' => 'Manual Client',
            'default_currency' => 'USD',
            'billing_report_token' => str_repeat('b', 64),
            'created_by_admin_id' => 1,
        ]);
    }

    private function domain(string $name, float $postPrice, string $priceColumn = 'post_price'): Domain
    {
        $domain = Domain::query()->create([
            'name' => $name,
            'domain_category_id' => 3,
            'admin_id' => 1,
        ]);

        LocalClientDomainCategoryPrice::query()->create([
            'local_client_id' => LocalClient::query()->value('id'),
            'domain_category_id' => 3,
            'post_price' => $priceColumn === 'post_price' ? $postPrice : null,
            'sidebar_price' => $priceColumn === 'sidebar_price' ? $postPrice : null,
            'hidden_links_price' => $priceColumn === 'hidden_links_price' ? $postPrice : null,
            'sticky_price' => $priceColumn === 'sticky_price' ? $postPrice : null,
        ]);

        return $domain;
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
}
