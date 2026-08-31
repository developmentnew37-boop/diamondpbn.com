<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sidebar Campaign Model
 *
 * Represents a sidebar/blogroll campaign that publishes links
 * to the sidebar/blogroll section of WordPress sites.
 *
 * @property int $id
 * @property string $campaign_no
 * @property int $domain_category_id
 * @property int $admin_id
 * @property int|null $sidebar_count
 * @property string $status
 * @property int $total_targets
 * @property int $completed_targets
 * @property int $failed_targets
 * @property string $report_token
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $last_bulk_updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
        'converted_to_schedule_sidebar_campaign_id',
        'conversion_locked_at',
        'local_client_id',
        'billing_total',
        'billing_amount_paid',
        'billing_currency',
        'billing_snapshot',
        'billing_payment_status',
        'billing_paid_at',
        'billing_paid_by_admin_id',
        'billing_payment_note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_bulk_updated_at' => 'datetime',
        'conversion_locked_at' => 'datetime',
        'billing_snapshot' => 'array',
        'billing_total' => 'decimal:2',
        'billing_amount_paid' => 'decimal:2',
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

    public function convertedScheduleCampaign()
    {
        return $this->belongsTo(ScheduleSidebarCampaign::class, 'converted_to_schedule_sidebar_campaign_id');
    }

    public function localClient()
    {
        return $this->belongsTo(LocalClient::class, 'local_client_id');
    }
}
