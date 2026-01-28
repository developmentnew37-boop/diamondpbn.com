<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleSidebarCampaign extends Model
{
    protected $table = 'schedule_sidebar_campaigns';

    protected $fillable = [
        'campaign_no',
        'admin_id',
        'domain_category_id',
        'schedule_from_date',
        'schedule_to_date',
        'status',
        'total_targets',
        'completed_targets',
        'failed_targets',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'schedule_from_date' => 'date',
        'schedule_to_date'   => 'date',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
    ];

    
    protected static function booted()
    {
        static::creating(function ($campaign) {
            if (empty($campaign->report_token)) {
                $campaign->report_token = Str::random(64);
            }
        });
    }


    /* =========================
     | Relationships
     ========================= */

    public function domainCategory()
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }


    public function links()
    {
        return $this->hasMany(
            ScheduleSidebarCampaignLink::class,
            'schedule_sidebar_campaign_id'
        );
    }

    public function domains()
    {
        return $this->hasMany(
            ScheduleSidebarCampaignDomain::class,
            'schedule_sidebar_campaign_id'
        );
    }

    public function tasks()
    {
        return $this->hasMany(
            ScheduleSidebarCampaignTask::class,
            'schedule_sidebar_campaign_id'
        );
    }
}
