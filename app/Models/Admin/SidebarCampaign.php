<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SidebarCampaign extends Model
{
    protected $fillable = [
        'campaign_no',
        'domain_category_id',
        'admin_id',
        'sidebar_count',
        'status',
        'total_targets',
        'completed_targets',
        'failed_targets',
        'started_at',
        'finished_at',
        'last_bulk_updated_at',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'finished_at'          => 'datetime',
        'last_bulk_updated_at' => 'datetime',
    ];



    protected static function booted()
    {
        static::creating(function ($campaign) {
            if (empty($campaign->report_token)) {
                $campaign->report_token = Str::random(64);
            }
        });
    }


    public function links()
    {
        return $this->hasMany(SidebarCampaignLink::class);
    }

    public function domains()
    {
        return $this->hasMany(SidebarCampaignDomain::class);
    }


    public function domainCategory()
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }


    public function tasks()
    {
        return $this->hasMany(SidebarCampaignTask::class);
    }
}
