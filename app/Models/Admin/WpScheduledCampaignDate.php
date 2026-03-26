<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class WpScheduledCampaignDate extends Model
{
    protected $table = 'wp_scheduled_campaign_dates';

    protected $fillable = [
        'wp_scheduled_campaign_id',
        'schedule_date',
        'quantity',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    public function campaign()
    {
        return $this->belongsTo(WpScheduledCampaign::class, 'wp_scheduled_campaign_id');
    }
}
