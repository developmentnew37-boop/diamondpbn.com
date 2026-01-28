<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SidebarCampaignTask extends Model
{

    protected $table = 'sidebar_campaign_tasks';

    protected $fillable = [
        'sidebar_campaign_id',
        'sidebar_campaign_domain_id',
        'sidebar_campaign_link_id', // ✅ ADD THIS LINE
        'status',
        'links_payload',
        'attempt_count',
        'max_attempts',
        'last_error',
        'next_retry_at',
        'locked_at',
        'lock_token',
        'locked_until',
        'remote_id',
        'remote_url',
        'http_status',
        'remote_response',
        'published_at',
        'started_at',
        'finished_at',
    ];


    protected $casts = [
        'links_payload' => 'array',      // if longtext, still ok (Laravel will json_encode/decode)
        'remote_response' => 'array',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'locked_until' => 'datetime',
        'published_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(SidebarCampaign::class, 'sidebar_campaign_id');
    }

    public function sidebarCampaign()
    {
        return $this->belongsTo(SidebarCampaign::class, 'sidebar_campaign_id');
    }

    public function domainRow()
    {
        return $this->belongsTo(SidebarCampaignDomain::class, 'sidebar_campaign_domain_id');
    }

    public function linkRow()
    {
        return $this->belongsTo(SidebarCampaignLink::class, 'sidebar_campaign_link_id');
    }
}
