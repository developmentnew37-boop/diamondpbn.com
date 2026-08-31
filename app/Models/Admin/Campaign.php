<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Campaign Model
 *
 * Represents a PBN (Private Blog Network) post campaign that publishes
 * articles with keyword/URL pairs to multiple WordPress domains.
 *
 * @property int $id
 * @property string $campaign_no
 * @property int $domain_category_id
 * @property int|null $article_category_id
 * @property int $admin_id
 * @property string|null $article_type
 * @property string $status
 * @property bool $is_sticky_campaign
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
class Campaign extends Model
{
    //
    protected $fillable = [
        'campaign_no',
        'domain_category_id',
        'article_category_id',
        'admin_id',
        'article_type',
        'status',
        'is_sticky_campaign',
        'total_targets',
        'completed_targets',
        'failed_targets',
        'converted_to_schedule_campaign_id',
        'conversion_locked_at',
        'started_at',
        'finished_at',
        'last_bulk_updated_at',
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
        'is_sticky_campaign' => 'boolean',
        'conversion_locked_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_bulk_updated_at' => 'datetime',
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

    public function domainCategory()
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }

    public function campaignArticles()
    {
        return $this->hasMany(CampaignArticle::class, 'campaign_id');
    }

    public function campaignDomains()
    {
        return $this->hasMany(CampaignDomain::class, 'campaign_id');
    }

    public function campaignPosts()
    {
        return $this->hasMany(CampaignPost::class, 'campaign_id');
    }

    public function domainReplacements()
    {
        return $this->hasMany(CampaignDomainReplacement::class, 'campaign_id');
    }

    public function convertedScheduleCampaign()
    {
        return $this->belongsTo(ScheduleCampaign::class, 'converted_to_schedule_campaign_id');
    }

    public function localClient()
    {
        return $this->belongsTo(LocalClient::class, 'local_client_id');
    }
}
