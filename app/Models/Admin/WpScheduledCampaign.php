<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * WordPress Scheduled Campaign Model
 *
 * Represents a campaign that uses WordPress's native scheduling system
 * to publish articles at future dates controlled by WordPress cron.
 *
 * @property int $id
 * @property string $campaign_no
 * @property int $domain_category_id
 * @property int|null $article_category_id
 * @property int $admin_id
 * @property string $status
 * @property int $total_targets
 * @property int $completed_targets
 * @property int $failed_targets
 * @property string $report_token
 * @property \Illuminate\Support\Carbon|null $schedule_from_date
 * @property \Illuminate\Support\Carbon|null $schedule_to_date
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WpScheduledCampaign extends Model
{
    protected $table = 'wp_scheduled_campaigns';

    protected $fillable = [
        'campaign_no',
        'admin_id',
        'domain_category_id',
        'article_category_id',
        'schedule_from_date',
        'schedule_to_date',
        'total_targets',
        'status',
        'completed_targets',
        'failed_targets',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'schedule_from_date' => 'date',
        'schedule_to_date'   => 'date',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $campaign) {
            if (empty($campaign->report_token)) {
                $campaign->report_token = Str::random(64);
            }
        });
    }

    public function admin()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'admin_id'); // App\Models\Admin (user)
    }

    public function domainCategory()
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }

    public function articleCategory()
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function dateRows()
    {
        return $this->hasMany(WpScheduledCampaignDate::class, 'wp_scheduled_campaign_id')->orderBy('schedule_date');
    }

    public function domains()
    {
        return $this->hasMany(WpScheduledCampaignDomain::class, 'wp_scheduled_campaign_id');
    }

    public function articles()
    {
        return $this->hasMany(WpScheduledCampaignArticle::class, 'wp_scheduled_campaign_id');
    }

    public function posts()
    {
        return $this->hasMany(WpScheduledCampaignPost::class, 'wp_scheduled_campaign_id');
    }
}
