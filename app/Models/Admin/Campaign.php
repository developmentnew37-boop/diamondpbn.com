<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

   

    public function campaignDomain()
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
