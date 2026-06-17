<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class CampaignArticle extends Model
{
    //
    protected $table = 'campaign_articles';

    protected $fillable = [
        'campaign_id',
        'article_id',
        'article_title_snapshot',
        'article_body_snapshot',
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

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function campaignPosts()
    {
        return $this->hasMany(CampaignPost::class, 'campaign_article_id');
    }
    // public function campaignPosts()
    // {
    //     return $this->hasOne(CampaignPost::class, 'campaign_article_id');
    // }
}
