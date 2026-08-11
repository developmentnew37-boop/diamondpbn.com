<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LocalClientBillLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'local_client_id',
        'domain_id',
        'domain_category_id',
        'billing_campaign_type',
        'unit_price',
        'line_total',
        'currency',
        'billable_type',
        'billable_id',
        'campaign_no',
        'snapshot',
        'created_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function localClient(): BelongsTo
    {
        return $this->belongsTo(LocalClient::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
