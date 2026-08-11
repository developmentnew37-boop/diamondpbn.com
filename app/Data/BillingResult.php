<?php

namespace App\Data;

use App\Models\Admin\BillingCampaignType;

class BillingResult
{
    /**
     * @param  list<array{
     *   domain_id: int,
     *   domain_name: string,
     *   category_id: int,
     *   category_name: string,
     *   billing_campaign_type: string,
     *   unit_price: string,
     *   line_total: string
     * }>  $lines
     */
    public function __construct(
        public readonly BillingCampaignType $billingType,
        public readonly string $total,
        public readonly array $lines,
    ) {}

    public function toSnapshot(string $currency): array
    {
        return [
            'total' => $this->total,
            'currency' => strtoupper($currency),
            'billing_campaign_type' => $this->billingType->value,
            'lines' => $this->lines,
        ];
    }
}
