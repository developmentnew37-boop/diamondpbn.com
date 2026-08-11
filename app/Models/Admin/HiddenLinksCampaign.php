<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Hidden Links Campaign Model
 *
 * Represents a hidden link campaign that adds non-visible links
 * to existing posts on WordPress sites.
 *
 * @property int $id
 * @property string $campaign_no
 * @property int $domain_category_id
 * @property int $admin_id
 * @property int|null $sidebar_count
 * @property string|null $domain_method
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
class HiddenLinksCampaign extends Model
{
    protected $table = 'hidden_links_campaigns';

    protected $fillable = [
        'campaign_no',
        'domain_category_id',
        'admin_id',
        'sidebar_count',
        'domain_method',
        'status',
        'total_targets',
        'completed_targets',
        'failed_targets',
        'started_at',
        'finished_at',
        'last_bulk_updated_at',
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
        'sidebar_count' => 'integer',
        'total_targets' => 'integer',
        'completed_targets' => 'integer',
        'failed_targets' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_bulk_updated_at' => 'datetime',
        'billing_snapshot' => 'array',
        'billing_total' => 'decimal:2',
        'billing_paid_at' => 'datetime',
    ];

    // =========================
    // Relationships
    // =========================

    protected static function booted()
    {
        static::creating(function ($campaign) {
            if (empty($campaign->report_token)) {
                $campaign->report_token = Str::random(64);
            }
        });
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function domainCategory()
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }

    public function links()
    {
        return $this->hasMany(HiddenLinksCampaignLinks::class, 'hidden_links_campaigns_id')
            ->orderBy('sort_order');
    }

    public function domains()
    {
        return $this->hasMany(HiddenLinksCampaignDomains::class, 'hidden_links_campaigns_id');
    }

    public function tasks()
    {
        return $this->hasMany(HiddenLinksCampaignTasks::class, 'hidden_links_campaigns_id');
    }

    // =========================
    // Helpful Scopes
    // =========================

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['queued', 'running', 'paused']);
    }

    public function localClient()
    {
        return $this->belongsTo(LocalClient::class, 'local_client_id');
    }
}
