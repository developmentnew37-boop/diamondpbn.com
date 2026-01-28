<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\HiddenLinksCampaignTasks;
use Illuminate\Support\Str;
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
    ];

    protected $casts = [
        'sidebar_count'      => 'integer',
        'total_targets'      => 'integer',
        'completed_targets'  => 'integer',
        'failed_targets'     => 'integer',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
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
}
