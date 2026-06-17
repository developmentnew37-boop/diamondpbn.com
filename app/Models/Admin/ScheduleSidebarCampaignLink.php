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
        'target_url_type',
        'anchor_keyword_type',
        'nofollow',
        'sponsored',
        'ugc',
        'noopener',
        'noreferrer',
        'sort_order',
        'raw_rel_attr',
    ];

    protected $casts = [
        'nofollow' => 'boolean',
        'sponsored' => 'boolean',
        'ugc' => 'boolean',
        'noopener' => 'boolean',
        'noreferrer' => 'boolean',
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
