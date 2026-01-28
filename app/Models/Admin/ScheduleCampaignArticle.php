<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleCampaignArticle extends Model
{

    protected $table = 'schedule_campaigns_articles';

   protected $fillable = [
        'schedule_campaign_id',
        'article_id',

        // 🔥 SNAPSHOT DATA
        'keyword',
        'url',
        'keyword_type',
        'url_type',
        'media',
        'nofollow',
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
