<?php

namespace Tests\Unit;

use App\Services\LocalClientBillingReportService;
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
        $this->assertTrue($filters['is_active']);
    }

    public function test_month_without_year_does_not_apply_month_filter(): void
    {
        $filters = $this->service->resolveFilters(Request::create('/', 'GET', [
            'filter_month' => '8',
        ]));

        $this->assertNull($filters['month']);
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
        $this->assertSame('hidden', $filters['campaign_type']);
        $this->assertTrue($filters['is_active']);
        $this->assertSame('Paid · Hidden Links', $filters['label']);
    }
}
