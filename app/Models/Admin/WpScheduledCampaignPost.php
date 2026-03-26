<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class WpScheduledCampaignPost extends Model
{
    protected $table = 'wp_scheduled_campaign_posts';

    protected $fillable = [
        'wp_scheduled_campaign_id',
        'wp_scheduled_campaign_article_id',
        'wp_scheduled_campaign_domain_id',
        'scheduled_date',
        'scheduled_at',
        'status',
        'attempt_count',
        'next_retry_at',
        'last_error',
        'locked_at',
        'lock_token',
        'remote_id',
        'remote_status',
        'remote_scheduled_at',
        'published_at',
        'http_status',
        'remote_title',
        'remote_url',
        'remote_response',
    ];

    protected $casts = [
        'scheduled_date'      => 'date',
        'scheduled_at'        => 'datetime',
        'next_retry_at'       => 'datetime',
        'locked_at'           => 'datetime',
        'remote_scheduled_at' => 'datetime',
        'published_at'        => 'datetime',
        'remote_response'     => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(WpScheduledCampaign::class, 'wp_scheduled_campaign_id');
    }

    public function campaignArticle()
    {
        return $this->belongsTo(WpScheduledCampaignArticle::class, 'wp_scheduled_campaign_article_id');
    }

    public function campaignDomain()
    {
        return $this->belongsTo(WpScheduledCampaignDomain::class, 'wp_scheduled_campaign_domain_id');
    }

    /**
     * For reporting: accurate status from our state + WordPress remote_status.
     * Uses scheduled_at (date + time), not scheduled_date (date only), so a post at 8:06 PM is not
     * "Missed schedule" at 6:10 PM on the same day.
     * - Missed schedule = remote_status future but scheduled_at (the actual time) has passed.
     * - Scheduled = scheduled_at is still in the future.
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'failed') {
            return 'Failed';
        }
        if ($this->status !== 'success') {
            return 'Queued';
        }
        if ($this->remote_status === 'publish') {
            return 'Live';
        }
        if (($this->remote_status === 'future' || $this->remote_status === 'missed-schedule') && $this->scheduled_at && $this->scheduled_at->isPast()) {
            return 'Missed schedule';
        }
        if ($this->scheduled_at && $this->scheduled_at->isFuture()) {
            return 'Scheduled';
        }
        if ($this->scheduled_date && $this->scheduled_date->isFuture()) {
            return 'Scheduled';
        }
        return $this->remote_status === 'publish' ? 'Live' : 'Queued';
    }

    /** Whether this post can be retried (re-send to WordPress). Only for failed/queued; missed schedule use Sync. */
    public function getCanRetryAttribute(): bool
    {
        return in_array($this->status, ['queued', 'failed', 'publishing'], true);
    }

    /** Whether we have a remote post and can sync status from WordPress. */
    public function getCanSyncAttribute(): bool
    {
        return !empty($this->remote_id);
    }
}
