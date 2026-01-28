<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleCampaignDomain extends Model
{
    protected $table = 'schedule_campaigns_domains';

    protected $fillable = [
        'schedule_campaign_id',
        'domain_id',
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

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function posts()
    {
        return $this->hasMany(
            ScheduleCampaignPost::class,
            'schedule_campaign_domain_id'
        );
    }
}
