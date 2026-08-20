<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleSidebarCampaignDomainReplacement extends Model
{
    protected $table = 'schedule_sidebar_campaign_domain_replacements';

    protected $fillable = [
        'request_uuid',
        'schedule_sidebar_campaign_id',
        'schedule_sidebar_campaign_task_id',
        'schedule_sidebar_campaign_domain_id',
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
        'previous_remote_id',
        'previous_remote_url',
        'old_remote_cleanup_status',
        'old_remote_cleanup_error',
        'old_remote_cleaned_at',
    ];

    protected $casts = [
        'health_snapshot' => 'array',
        'dispatch_generation' => 'integer',
        'old_remote_cleaned_at' => 'datetime',
    ];
}
