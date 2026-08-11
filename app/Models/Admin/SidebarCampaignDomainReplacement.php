<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SidebarCampaignDomainReplacement extends Model
{
    protected $table = 'sidebar_campaign_domain_replacements';

    protected $fillable = [
        'request_uuid',
        'sidebar_campaign_id',
        'sidebar_campaign_task_id',
        'sidebar_campaign_domain_id',
        'old_domain_id',
        'new_domain_id',
        'old_hostname',
        'new_hostname',
        'admin_id',
        'reason',
        'previous_status',
        'result_status',
        'health_snapshot',
        'dispatch_generation',
        'state',
        'error',
    ];

    protected $casts = [
        'health_snapshot' => 'array',
        'dispatch_generation' => 'integer',
    ];
}
