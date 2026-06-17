<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin;
use Illuminate\Support\Str;

/**
 * Schedule Campaign Model
 *
 * Represents a scheduled campaign that publishes articles with keyword/URL pairs
 * to multiple WordPress domains at specified future dates.
 *
 * @property int $id
 * @property string $campaign_no
 * @property int $domain_category_id
 * @property int|null $article_category_id
 * @property int $admin_id
 * @property string $status
 * @property bool $is_sticky_campaign
 * @property int $total_targets
 * @property int $completed_targets
 * @property int $failed_targets
 * @property string $report_token
 * @property \Illuminate\Support\Carbon|null $schedule_from_date
 * @property \Illuminate\Support\Carbon|null $schedule_to_date
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $last_bulk_updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ScheduleCampaign extends Model
{
    protected $table = 'schedule_campaigns';

    protected $fillable = [
        'campaign_no',
        'admin_id',
        'domain_category_id',
        'article_category_id',
        'total_targets',
        'is_sticky_campaign',
        'schedule_from_date',
        'schedule_to_date',
        'status',
        'completed_targets',
        'failed_targets',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'is_sticky_campaign' => 'boolean',
        'schedule_from_date' => 'date',
        'schedule_to_date'   => 'date',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
    ];


    /* =======================
       RELATIONSHIPS
    ======================= */

    protected static function booted()
    {
        static::creating(function ($campaign) {
            if (empty($campaign->report_token)) {
                $campaign->report_token = Str::random(64);
            }
        });
    }


    public function articles()
    {
        return $this->hasMany(
            ScheduleCampaignArticle::class,
            'schedule_campaign_id'
        );
    }

    public function domainCategory()
    {
        return $this->belongsTo(
            DomainCategory::class,
            'domain_category_id'
        );
    }

    public function domains()
    {
        return $this->hasMany(
            ScheduleCampaignDomain::class,
            'schedule_campaign_id'
        );
    }

    public function dateRows()
    {
        return $this->hasMany(
            ScheduleCampaignDate::class,
            'schedule_campaign_id'
        )->orderBy('schedule_date');
    }

    public function posts()
    {
        return $this->hasMany(
            ScheduleCampaignPost::class,
            'schedule_campaign_id'
        );
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
