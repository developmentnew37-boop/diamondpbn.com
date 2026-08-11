<?php

namespace Tests\Unit;

use App\Services\LocalClientCampaignInvoiceService;
use Tests\TestCase;

class LocalClientCampaignInvoiceServiceTest extends TestCase
{
    public function test_it_groups_invoice_lines_by_domain_category_with_quantity(): void
    {
        $service = app(LocalClientCampaignInvoiceService::class);

        $items = $service->groupLinesForInvoice([
            ['category_id' => 2, 'category_name' => '.co.uk', 'unit_price' => '12.00', 'line_total' => '12.00'],
            ['category_id' => 2, 'category_name' => '.co.uk', 'unit_price' => '12.00', 'line_total' => '12.00'],
            ['category_id' => 2, 'category_name' => '.co.uk', 'unit_price' => '12.00', 'line_total' => '12.00'],
            ['category_id' => 1, 'category_name' => 'old .com domains', 'unit_price' => '10.00', 'line_total' => '10.00'],
            ['category_id' => 1, 'category_name' => 'old .com domains', 'unit_price' => '10.00', 'line_total' => '10.00'],
        ], 'PBN Post');

        $this->assertCount(2, $items);

        $coUk = collect($items)->firstWhere('title', 'PBN Post — .co.uk');
        $this->assertNotNull($coUk);
        $this->assertSame(3, $coUk['quantity']);
        $this->assertSame(12.0, $coUk['price']);
        $this->assertSame(36.0, $coUk['subtotal']);

        $com = collect($items)->firstWhere('title', 'PBN Post — old .com domains');
        $this->assertNotNull($com);
        $this->assertSame(2, $com['quantity']);
        $this->assertSame(10.0, $com['price']);
        $this->assertSame(20.0, $com['subtotal']);
    }

    public function test_it_splits_same_category_when_unit_prices_differ(): void
    {
        $service = app(LocalClientCampaignInvoiceService::class);

        $items = $service->groupLinesForInvoice([
            ['category_id' => 2, 'category_name' => '.co.uk', 'unit_price' => '12.00', 'line_total' => '12.00'],
            ['category_id' => 2, 'category_name' => '.co.uk', 'unit_price' => '15.00', 'line_total' => '15.00'],
        ], 'PBN Post');

        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]['quantity']);
        $this->assertSame(1, $items[1]['quantity']);
    }
}
