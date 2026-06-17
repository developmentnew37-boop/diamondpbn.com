<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleCampaignArticle extends Model
{

    protected $table = 'schedule_campaigns_articles';

   protected $fillable = [
        'schedule_campaign_id',
        'article_id',
        'article_title_snapshot',
        'article_body_snapshot',

        // 🔥 SNAPSHOT DATA
        'keyword',
        'url',
        'keyword_type',
        'url_type',
        'media',
        'nofollow',
        'sponsored',
        'ugc',
        'noopener',
        'noreferrer',
        'raw_rel_attr',
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

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function posts()
    {
        return $this->hasMany(
            ScheduleCampaignPost::class,
            'schedule_campaign_article_id'
        );
    }
}
