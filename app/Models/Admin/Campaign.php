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
        'started_at',
        'finished_at',
        'last_bulk_updated_at',
    ];

    protected $casts = [
        'started_at'            => 'datetime',
        'finished_at'           => 'datetime',
        'last_bulk_updated_at'  => 'datetime',
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
}
