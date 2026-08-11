<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalClientBillingPeriod extends Model
{
    protected $fillable = [
        'local_client_id',
        'period_start',
        'period_end',
        'campaign_count',
        'total_amount',
        'currency',
        'paid_at',
        'paid_by_admin_id',
        'payment_note',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function localClient(): BelongsTo
    {
        return $this->belongsTo(LocalClient::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'paid_by_admin_id');
    }
}
