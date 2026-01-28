<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SidebarCampaignDomain extends Model
{
    protected $fillable = [
        'sidebar_campaign_id',
        'domain_id',
    ];

    public function campaign()
    {
        return $this->belongsTo(SidebarCampaign::class, 'sidebar_campaign_id');
    }

    public function tasks()
    {
        return $this->hasMany(SidebarCampaignTask::class, 'sidebar_campaign_domain_id');
    }
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
