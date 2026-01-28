<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class HiddenLinksCampaignLinks extends Model
{
    protected $table = 'hidden_links_campaigns_links';

    protected $fillable = [
        'hidden_links_campaigns_id',
        'sort_order',
        'target_url',
        'anchor_keyword',
        'nofollow',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'nofollow'   => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(HiddenLinksCampaign::class, 'hidden_links_campaigns_id');
    }

    public function tasks()
    {
        return $this->hasMany(HiddenLinksCampaignTasks::class, 'hidden_links_campaigns_link_id');
    }
}
