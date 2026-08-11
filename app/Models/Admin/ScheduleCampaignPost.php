<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleCampaignPost extends Model
{
    protected $table = 'schedule_campaigns_posts';

    protected $fillable = [
        'schedule_campaign_id',
        'source_campaign_post_id',
        'is_converted_live',
        'conversion_phase',
        'conversion_publish_date',
        'last_conversion_error',
        'last_conversion_attempt_at',
        'schedule_campaign_article_id',
        'schedule_campaign_domain_id',
        'schedule_at',
        'status',
        'attempt_count',
        'dispatch_generation',
        'last_attempt_at',
        'next_retry_at',
        'last_error',
        'locked_at',
        'lock_token',
        'remote_id',
        'remote_title',
        'remote_url',
        'http_status',
        'remote_status',
        'remote_response',
        'published_at',
    ];

    protected $casts = [
        'schedule_at' => 'datetime',
        'is_converted_live' => 'boolean',
        'conversion_publish_date' => 'date',
        'last_conversion_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'published_at' => 'datetime',
        'remote_response' => 'array',
    ];

    /* =======================
       RELATIONSHIPS
    ======================= */

    public function campaign()
    {
        return $this->belongsTo(
            ScheduleCampaign::class,
            'schedule_campaign_id'
        );
    }

    public function scheduleCampaign()
    {
        return $this->belongsTo(
            ScheduleCampaign::class,
            'schedule_campaign_id'
        );
    }

    public function campaignArticle()
    {
        return $this->belongsTo(
            ScheduleCampaignArticle::class,
            'schedule_campaign_article_id'
        );
    }

    public function campaignDomain()
    {
        return $this->belongsTo(
            ScheduleCampaignDomain::class,
            'schedule_campaign_domain_id'
        );
    }
}
