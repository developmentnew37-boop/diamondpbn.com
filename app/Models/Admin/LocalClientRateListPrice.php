<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalClientRateListPrice extends Model
{
    protected $fillable = [
        'rate_list_id',
        'domain_category_id',
        'post_price',
        'sidebar_price',
        'hidden_links_price',
        'sticky_price',
    ];

    protected $casts = [
        'post_price' => 'decimal:2',
        'sidebar_price' => 'decimal:2',
        'hidden_links_price' => 'decimal:2',
        'sticky_price' => 'decimal:2',
    ];

    public function rateList(): BelongsTo
    {
        return $this->belongsTo(LocalClientRateList::class, 'rate_list_id');
    }

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class);
    }

    public function priceForType(BillingCampaignType $type): ?string
    {
        return match ($type) {
            BillingCampaignType::Post => $this->post_price,
            BillingCampaignType::Sidebar => $this->sidebar_price,
            BillingCampaignType::HiddenLinks => $this->hidden_links_price,
            BillingCampaignType::Sticky => $this->sticky_price,
        };
    }
}
