<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SidebarCampaignLink extends Model
{
    protected $fillable = [
        'sidebar_campaign_id',
        'sort_order',
        'target_url',
        'anchor_keyword',
        'nofollow',
    ];

    protected $casts = [
        'nofollow' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(SidebarCampaign::class, 'sidebar_campaign_id');
    }

    public function tasks()
    {
        return $this->hasMany(SidebarCampaignTask::class, 'sidebar_campaign_link_id');
    }
}
