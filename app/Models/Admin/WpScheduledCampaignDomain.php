<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class WpScheduledCampaignDomain extends Model
{
    protected $table = 'wp_scheduled_campaign_domains';

    protected $fillable = [
        'wp_scheduled_campaign_id',
        'domain_id',
    ];

    public function campaign()
    {
        return $this->belongsTo(WpScheduledCampaign::class, 'wp_scheduled_campaign_id');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function posts()
    {
        return $this->hasMany(WpScheduledCampaignPost::class, 'wp_scheduled_campaign_domain_id');
    }
}
