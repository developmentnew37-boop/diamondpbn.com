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
        'converted_from_sidebar_campaign_id',
        'conversion_run_date',
        'conversion_mode',
        'conversion_pipeline_status',
        'local_client_id',
        'billing_total',
        'billing_currency',
        'billing_snapshot',
        'billing_payment_status',
        'billing_paid_at',
        'billing_paid_by_admin_id',
        'billing_payment_note',
    ];

    protected $casts = [
        'schedule_from_date' => 'date',
        'schedule_to_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'conversion_run_date' => 'date',
        'billing_snapshot' => 'array',
        'billing_total' => 'decimal:2',
        'billing_paid_at' => 'datetime',
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

    public function dateRows()
    {
        return $this->hasMany(
            ScheduleSidebarCampaignDate::class,
            'schedule_sidebar_campaign_id'
        )->orderBy('schedule_date');
    }

    public function sourceSidebarCampaign()
    {
        return $this->belongsTo(SidebarCampaign::class, 'converted_from_sidebar_campaign_id');
    }

    /**
     * Set finished_at and status from current counts when all tasks are done.
     * Call after changing completed_targets/failed_targets (e.g. after deleting a task)
     * so status shows 'completed' when all remaining tasks succeeded.
     */
    public function syncStatusFromCounts(): void
    {
        if ($this->total_targets <= 0) {
            return;
        }
        $totalDone = $this->completed_targets + $this->failed_targets;
        if ($totalDone < $this->total_targets) {
            return;
        }
        if ($this->finished_at === null) {
            $this->finished_at = now();
        }
        if ($this->failed_targets === 0) {
            $this->status = 'completed';
        } elseif ($this->completed_targets > 0) {
            $this->status = 'semi_failed';
        } else {
            $this->status = 'failed';
        }
        $this->save();
    }

    public function localClient()
    {
        return $this->belongsTo(LocalClient::class, 'local_client_id');
    }
}
