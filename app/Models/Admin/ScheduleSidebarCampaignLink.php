<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleSidebarCampaignLink extends Model
{
    protected $table = 'schedule_sidebar_campaign_links';

    protected $fillable = [
        'schedule_sidebar_campaign_id',
        'target_url',
        'anchor_keyword',
        'nofollow',
        'sort_order',
    ];

    public function campaign()
    {
        return $this->belongsTo(
            ScheduleSidebarCampaign::class,
            'schedule_sidebar_campaign_id'
        );
    }

    public function tasks()
    {
        return $this->hasMany(
            ScheduleSidebarCampaignTask::class,
            'schedule_sidebar_campaign_link_id'
        );
    }
}
