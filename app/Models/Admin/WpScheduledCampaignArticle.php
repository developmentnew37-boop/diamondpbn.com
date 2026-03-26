<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class WpScheduledCampaignArticle extends Model
{
    protected $table = 'wp_scheduled_campaign_articles';

    protected $fillable = [
        'wp_scheduled_campaign_id',
        'article_id',
        'keyword',
        'url',
        'keyword_type',
        'url_type',
        'nofollow',
        'media',
    ];

    public function campaign()
    {
        return $this->belongsTo(WpScheduledCampaign::class, 'wp_scheduled_campaign_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function posts()
    {
        return $this->hasMany(WpScheduledCampaignPost::class, 'wp_scheduled_campaign_article_id');
    }
}
