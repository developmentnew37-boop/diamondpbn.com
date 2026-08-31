<?php

namespace Tests\Unit;

use App\Services\LocalClientBillingReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class LocalClientBillingReportServiceTest extends TestCase
{
    private LocalClientBillingReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LocalClientBillingReportService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_filter_by_month(): void
    {
        $campaigns = collect([
            ['created_at' => '2026-08-06 10:00:00', 'billing_total' => 50],
            ['created_at' => '2026-07-15 10:00:00', 'billing_total' => 30],
            ['created_at' => '2026-08-20 10:00:00', 'billing_total' => 20],
        ]);

        $filtered = $this->service->filterCampaigns($campaigns, [
            'month' => '2026-08',
            'from_date' => null,
            'to_date' => null,
            'status' => null,
            'campaign_type' => null,
        ]);

        $this->assertCount(2, $filtered);
        $this->assertSame('2026-08-06 10:00:00', $filtered->first()['created_at']);
    }

    public function test_filter_by_date_range(): void
    {
        $campaigns = collect([
            ['created_at' => '2026-08-01 10:00:00', 'billing_total' => 10],
            ['created_at' => '2026-08-10 10:00:00', 'billing_total' => 20],
            ['created_at' => '2026-08-20 10:00:00', 'billing_total' => 30],
        ]);

        $filtered = $this->service->filterCampaigns($campaigns, [
            'month' => null,
            'from_date' => '2026-08-05',
            'to_date' => '2026-08-15',
            'status' => null,
            'campaign_type' => null,
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('2026-08-10 10:00:00', $filtered->first()['created_at']);
    }

    public function test_month_filter_takes_priority_over_date_range(): void
    {
        $campaigns = collect([
            ['created_at' => '2026-08-06 10:00:00', 'billing_total' => 50],
            ['created_at' => '2026-07-06 10:00:00', 'billing_total' => 30],
        ]);

        $filtered = $this->service->filterCampaigns($campaigns, [
            'month' => '2026-08',
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
            'status' => null,
            'campaign_type' => null,
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('2026-08-06 10:00:00', $filtered->first()['created_at']);
    }

    public function test_resolve_filters_swaps_invalid_date_range(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'from_date' => '2026-08-20',
            'to_date' => '2026-08-01',
        ]));

        $this->assertSame('2026-08-01', $filters['from_date']);
        $this->assertSame('2026-08-20', $filters['to_date']);
        $this->assertTrue($filters['is_active']);
    }

    public function test_available_years_are_unique_and_sorted_desc(): void
    {
        $campaigns = collect([
            ['created_at' => '2026-08-06 10:00:00'],
            ['created_at' => '2026-07-15 10:00:00'],
            ['created_at' => '2025-08-20 10:00:00'],
        ]);

        $years = $this->service->availableYears($campaigns);

        $this->assertSame(['2026', '2025'], $years->all());
    }

    public function test_available_years_includes_gap_years_with_no_campaigns(): void
    {
        $campaigns = collect([
            ['created_at' => '2024-03-01 10:00:00'],
            ['created_at' => '2026-08-06 10:00:00'],
        ]);

        $years = $this->service->availableYears($campaigns);

        $this->assertSame(['2026', '2025', '2024'], $years->all());
    }

    public function test_resolve_filters_requires_year_and_month_for_month_filter(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'filter_year' => '2026',
            'filter_month' => '8',
        ]));

        $this->assertSame('2026', $filters['year']);
        $this->assertSame('08', $filters['month_num']);
        $this->assertSame('2026-08', $filters['month']);
        $this->assertFalse($filters['is_default_month']);
        $this->assertTrue($filters['is_active']);
    }

    public function test_month_without_year_falls_back_to_current_month(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'filter_month' => '8',
        ]));

        $this->assertSame((string) now()->year, $filters['year']);
        $this->assertSame(now()->format('m'), $filters['month_num']);
        $this->assertSame(now()->format('Y-m'), $filters['month']);
        $this->assertTrue($filters['is_default_month']);
        $this->assertFalse($filters['is_active']);
    }

    public function test_filter_by_status_and_campaign_type(): void
    {
        $campaigns = collect([
            ['created_at' => '2026-08-06 10:00:00', 'type' => 'campaign', 'billing_payment_status' => 'paid'],
            ['created_at' => '2026-08-07 10:00:00', 'type' => 'sidebar_campaign', 'billing_payment_status' => 'unpaid'],
            ['created_at' => '2026-08-08 10:00:00', 'type' => 'hidden_links_campaign', 'billing_payment_status' => 'unpaid'],
        ]);

        $filtered = $this->service->filterCampaigns($campaigns, [
            'month' => null,
            'from_date' => null,
            'to_date' => null,
            'status' => 'unpaid',
            'campaign_type' => 'sidebar',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('sidebar_campaign', $filtered->first()['type']);
    }

    public function test_resolve_filters_includes_status_and_type(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'status' => 'paid',
            'campaign_type' => 'hidden',
        ]));

        $this->assertSame('paid', $filters['status']);
        $this->assertSame('paid', $filters['status_selection']);
        $this->assertSame('hidden', $filters['campaign_type']);
        $this->assertTrue($filters['is_default_month']);
        $this->assertTrue($filters['is_active']);
        $this->assertSame(now()->format('F Y').' · Paid · Hidden Links', $filters['label']);
    }

    public function test_resolve_filters_defaults_to_current_month_and_unpaid(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET'));

        $this->assertSame('unpaid', $filters['status']);
        $this->assertSame('unpaid', $filters['status_selection']);
        $this->assertSame((string) now()->year, $filters['year']);
        $this->assertSame(now()->format('m'), $filters['month_num']);
        $this->assertSame(now()->format('Y-m'), $filters['month']);
        $this->assertTrue($filters['is_default_month']);
        $this->assertFalse($filters['is_active']);
        $this->assertSame(now()->format('F Y'), $filters['label']);
    }

    public function test_resolve_filters_status_all_shows_paid_and_unpaid(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'status' => 'all',
            'filter_year' => '2026',
            'filter_month' => '08',
        ]));

        $this->assertNull($filters['status']);
        $this->assertSame('all', $filters['status_selection']);
        $this->assertFalse($filters['is_default_month']);
        $this->assertTrue($filters['is_active']);
        $this->assertSame('August 2026 · All statuses', $filters['label']);

        $campaigns = collect([
            ['created_at' => '2026-08-06 10:00:00', 'billing_payment_status' => 'paid'],
            ['created_at' => '2026-08-07 10:00:00', 'billing_payment_status' => 'unpaid'],
        ]);

        $filtered = $this->service->filterCampaigns($campaigns, $filters);
        $this->assertCount(2, $filtered);
    }

    public function test_date_range_skips_default_month(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
        ]));

        $this->assertNull($filters['month']);
        $this->assertFalse($filters['is_default_month']);
        $this->assertSame('2026-07-01', $filters['from_date']);
        $this->assertSame('2026-07-31', $filters['to_date']);
        $this->assertTrue($filters['is_active']);
    }

    public function test_status_counts_and_filters_without_status(): void
    {
        $campaigns = collect([
            ['billing_payment_status' => 'paid'],
            ['billing_payment_status' => 'paid'],
            ['billing_payment_status' => 'unpaid'],
        ]);

        $this->assertSame([
            'all' => 3,
            'paid' => 2,
            'unpaid' => 1,
        ], $this->service->statusCounts($campaigns));

        $filters = [
            'month' => null,
            'from_date' => null,
            'to_date' => null,
            'status' => 'unpaid',
            'status_selection' => 'unpaid',
            'campaign_type' => null,
        ];

        $withoutStatus = $this->service->filtersWithoutStatus($filters);
        $this->assertNull($withoutStatus['status']);
    }

    public function test_overall_unpaid_summary_groups_by_month_newest_first(): void
    {
        $campaigns = collect([
            [
                'created_at' => '2026-01-15 10:00:00',
                'billing_total' => 2300,
                'billing_payment_status' => 'unpaid',
            ],
            [
                'created_at' => '2026-05-10 10:00:00',
                'billing_total' => 16000,
                'billing_payment_status' => 'unpaid',
            ],
            [
                'created_at' => '2026-06-20 10:00:00',
                'billing_total' => 21000,
                'billing_payment_status' => 'unpaid',
            ],
            [
                'created_at' => '2026-08-01 10:00:00',
                'billing_total' => 500,
                'billing_payment_status' => 'paid',
            ],
        ]);

        $overall = $this->service->formatOverallUnpaidSummary($campaigns, 'USD');

        $this->assertTrue($overall['has_unpaid']);
        $this->assertSame(3, $overall['unpaid_count']);
        $this->assertSame(39300.0, $overall['total_unpaid']);
        $this->assertCount(3, $overall['breakdown']);
        $this->assertSame('2026-06', $overall['breakdown'][0]['month_key']);
        $this->assertSame('2026-05', $overall['breakdown'][1]['month_key']);
        $this->assertSame('2026-01', $overall['breakdown'][2]['month_key']);
        $this->assertSame(21000.0, $overall['breakdown'][0]['amount']);
    }

    public function test_overall_unpaid_summary_uses_balance_due_for_partial_pay(): void
    {
        $campaigns = collect([
            [
                'created_at' => '2026-05-10 10:00:00',
                'billing_total' => 100,
                'billing_amount_paid' => 60,
                'billing_balance_due' => '40.00',
                'billing_has_credit' => true,
                'billing_payment_status' => 'unpaid',
            ],
        ]);

        $overall = $this->service->formatOverallUnpaidSummary($campaigns, 'USD');

        $this->assertSame(40.0, $overall['total_unpaid']);
        $this->assertSame(40.0, $overall['breakdown'][0]['amount']);
    }

    public function test_month_filter_on_day_31_does_not_shift_may_or_june_ranges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00'));

        $campaigns = collect([
            [
                'created_at' => '2026-05-15 10:00:00',
                'billing_total' => 180,
                'billing_payment_status' => 'unpaid',
            ],
        ]);

        $mayFiltered = $this->service->filterCampaigns($campaigns, [
            'month' => '2026-05',
            'from_date' => null,
            'to_date' => null,
            'status' => null,
            'campaign_type' => null,
        ]);

        $juneFiltered = $this->service->filterCampaigns($campaigns, [
            'month' => '2026-06',
            'from_date' => null,
            'to_date' => null,
            'status' => null,
            'campaign_type' => null,
        ]);

        $this->assertCount(1, $mayFiltered);
        $this->assertCount(0, $juneFiltered);
    }

    public function test_breakdown_label_matches_month_key_on_day_31(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00'));

        $campaigns = collect([
            [
                'created_at' => '2026-05-15 10:00:00',
                'billing_total' => 180,
                'billing_payment_status' => 'unpaid',
            ],
        ]);

        $overall = $this->service->formatOverallUnpaidSummary($campaigns, 'USD');

        $this->assertSame('2026-05', $overall['breakdown'][0]['month_key']);
        $this->assertSame('May 2026', $overall['breakdown'][0]['label']);
    }

    public function test_resolve_filters_june_label_is_not_july_on_day_31(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00'));

        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'filter_year' => '2026',
            'filter_month' => '06',
            'status' => 'all',
        ]));

        $this->assertSame('2026-06', $filters['month']);
        $this->assertSame('June 2026 · All statuses', $filters['label']);
    }

    public function test_june_filter_on_day_15_still_correct(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $campaigns = collect([
            [
                'created_at' => '2026-06-20 10:00:00',
                'billing_total' => 100,
                'billing_payment_status' => 'unpaid',
            ],
        ]);

        $juneFiltered = $this->service->filterCampaigns($campaigns, [
            'month' => '2026-06',
            'from_date' => null,
            'to_date' => null,
            'status' => null,
            'campaign_type' => null,
        ]);

        $mayFiltered = $this->service->filterCampaigns($campaigns, [
            'month' => '2026-05',
            'from_date' => null,
            'to_date' => null,
            'status' => null,
            'campaign_type' => null,
        ]);

        $this->assertCount(1, $juneFiltered);
        $this->assertCount(0, $mayFiltered);
    }
}
