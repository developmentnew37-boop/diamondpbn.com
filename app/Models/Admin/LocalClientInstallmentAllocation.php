<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalClientInstallmentAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'installment_id',
        'billable_type',
        'billable_id',
        'deleted_campaign_billing_id',
        'campaign_no',
        'amount',
        'previous_collected_amount',
        'resulting_collected_amount',
        'billing_total',
        'old_status',
        'new_status',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'previous_collected_amount' => 'decimal:2',
        'resulting_collected_amount' => 'decimal:2',
        'billing_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LocalClientInstallment::class, 'installment_id');
    }
}
