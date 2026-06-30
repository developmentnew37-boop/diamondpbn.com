<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginDeploymentItem extends Model
{
    protected $fillable = [
        'plugin_deployment_id',
        'domain',
        'sort_order',
        'item_status',
        'operation_result',
        'version_before',
        'version_after',
        'plugin_file',
        'resolved_via',
        'error_code',
        'message',
        'attempts',
        'response_time_ms',
        'in_inventory',
        'domain_id',
        'category',
        'processed_at',
    ];

    protected $casts = [
        'in_inventory' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(PluginDeployment::class, 'plugin_deployment_id');
    }
}
