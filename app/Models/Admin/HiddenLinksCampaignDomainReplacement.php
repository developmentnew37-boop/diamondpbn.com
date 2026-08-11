<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class HiddenLinksCampaignDomainReplacement extends Model
{
    protected $table = 'hidden_links_campaign_domain_replacements';

    protected $fillable = [
        'request_uuid',
        'hidden_links_campaign_id',
        'hidden_links_campaign_task_id',
        'hidden_links_campaign_domain_id',
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
