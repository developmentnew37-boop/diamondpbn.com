<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalClientDeletedCampaignBilling extends Model
{
    protected $fillable = [
        'local_client_id',
        'billable_type',
        'billable_id',
        'campaign_type',
        'campaign_no',
        'billing_total',
        'billing_amount_paid',
        'billing_currency',
        'billing_payment_status',
        'billing_paid_at',
        'campaign_created_at',
    ];

    protected $casts = [
        'billing_total' => 'decimal:2',
        'billing_amount_paid' => 'decimal:2',
        'billing_paid_at' => 'datetime',
        'campaign_created_at' => 'datetime',
    ];

    public function localClient(): BelongsTo
    {
        return $this->belongsTo(LocalClient::class);
    }
}
