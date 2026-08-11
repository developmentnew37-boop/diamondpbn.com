<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class CampaignPost extends Model
{
    public const DELIVERY_NOT_ATTEMPTED = 'not_attempted';

    public const DELIVERY_REMOTE_ABSENT = 'remote_absent';

    public const DELIVERY_REMOTE_UNKNOWN = 'remote_unknown';

    public const DELIVERY_REMOTE_CREATED = 'remote_created';

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
        'delivery_state',
        'last_failure_code',
        'dispatch_generation',
        'auto_replace_domains',
    ];

    protected $casts = [
        'is_sticky' => 'boolean',
        'auto_replace_domains' => 'boolean',
        'published_at' => 'datetime',
        'content_updated_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'locked_until' => 'datetime',
        'dispatch_generation' => 'integer',
    ];

    public function isReplacementEligible(): bool
    {
        return in_array($this->delivery_state, [
            self::DELIVERY_NOT_ATTEMPTED,
            self::DELIVERY_REMOTE_ABSENT,
        ], true);
    }

    public function hasAmbiguousRemoteDelivery(): bool
    {
        return $this->delivery_state === self::DELIVERY_REMOTE_UNKNOWN;
    }

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

    public function domainReplacements()
    {
        return $this->hasMany(CampaignDomainReplacement::class, 'campaign_post_id');
    }
}
