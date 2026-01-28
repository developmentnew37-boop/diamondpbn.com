<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleSidebarCampaignTask extends Model
{
    protected $table = 'schedule_sidebar_campaign_tasks';

    protected $fillable = [
        'schedule_sidebar_campaign_id',
        'schedule_sidebar_campaign_domain_id',
        'schedule_sidebar_campaign_link_id',
        'schedule_at',
        'status',
        'attempt_count',
        'next_retry_at',
        'last_error',
    ];

    protected $casts = [
        'schedule_at'   => 'datetime',
         'published_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    /* =========================
     | Relationships
     ========================= */

    public function campaign()
    {
        return $this->belongsTo(
            ScheduleSidebarCampaign::class,
            'schedule_sidebar_campaign_id'
        );
    }

    public function domain()
    {
        return $this->belongsTo(
            ScheduleSidebarCampaignDomain::class,
            'schedule_sidebar_campaign_domain_id'
        );
    }

    public function link()
    {
        return $this->belongsTo(
            ScheduleSidebarCampaignLink::class,
            'schedule_sidebar_campaign_link_id'
        );
    }
    
}
