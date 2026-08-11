<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleCampaignDomainReplacement extends Model
{
    protected $table = 'schedule_campaign_domain_replacements';

    protected $fillable = [
        'request_uuid',
        'schedule_campaign_id',
        'schedule_campaign_post_id',
        'schedule_campaign_domain_id',
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
