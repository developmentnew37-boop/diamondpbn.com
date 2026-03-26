<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class CampaignPost extends Model
{
    //
    protected $table = 'campaign_posts';

    protected $fillable = [
        'campaign_id',
        'campaign_domain_id',
        'campaign_article_id',
        'status',
        'is_sticky',
        'remote_id',
        'remote_title',
        'remote_url',
        'published_at',
        'content_updated_at',
        'attempt_count',
        'last_error',
        'next_retry_at',
        'locked_at',
        'lock_token',
        'locked_until', // ✅ ADD
    ];

    protected $casts = [
        'published_at'      => 'datetime',
        'content_updated_at' => 'datetime',
        'next_retry_at'     => 'datetime',
        'locked_at'         => 'datetime',
        'locked_until'      => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function campaignDomain()
    {
        return $this->belongsTo(CampaignDomain::class, 'campaign_domain_id');
    }

    public function campaignArticle()
    {
        return $this->belongsTo(CampaignArticle::class, 'campaign_article_id');
    }
}
