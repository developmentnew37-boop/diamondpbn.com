<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleSidebarCampaignDomain extends Model
{
    protected $table = 'schedule_sidebar_campaign_domains';

    protected $fillable = [
        'schedule_sidebar_campaign_id',
        'domain_id',
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
            Domain::class,
            'domain_id'
        );
    }

    public function tasks()
    {
        return $this->hasMany(
            ScheduleSidebarCampaignTask::class,
            'schedule_sidebar_campaign_domain_id'
        );
    }
}
