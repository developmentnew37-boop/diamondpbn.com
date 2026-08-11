<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainStatusCheckItem extends Model
{
    protected $fillable = [
        'domain_status_check_id',
        'domain',
        'sort_order',
        'check_status',
        'connected',
        'status_code',
        'probe_method',
        'agent_version',
        'http_status',
        'message',
        'attempts',
        'response_time_ms',
        'in_inventory',
        'domain_id',
        'category',
        'checked_at',
    ];

    protected $casts = [
        'connected' => 'boolean',
        'in_inventory' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(DomainStatusCheck::class, 'domain_status_check_id');
    }
}
