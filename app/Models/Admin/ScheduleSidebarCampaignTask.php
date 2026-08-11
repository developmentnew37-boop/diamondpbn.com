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
        'schedule_sidebar_campaign_date_id',
        'schedule_at',
        'original_schedule_at',
        'status',
        'attempt_count',
        'dispatch_generation',
        'next_retry_at',
        'last_error',
        'locked_at',
        'lock_token',
        'remote_id',
        'remote_url',
        'http_status',
        'remote_response',
        'published_at',
        'source_sidebar_campaign_task_id',
        'is_converted_live',
        'conversion_phase',
        'conversion_publish_date',
        'last_conversion_error',
        'last_conversion_attempt_at',
        'remote_status',
    ];

    protected $casts = [
        'schedule_at' => 'datetime',
        'original_schedule_at' => 'datetime',
        'published_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'is_converted_live' => 'boolean',
        'conversion_publish_date' => 'date',
        'last_conversion_attempt_at' => 'datetime',
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

    /**
     * Date row this task was created from (when using date table). Report uses this for display so date is never overwritten by jobs.
     */
    public function scheduleDate()
    {
        return $this->belongsTo(
            ScheduleSidebarCampaignDate::class,
            'schedule_sidebar_campaign_date_id'
        );
    }
}
