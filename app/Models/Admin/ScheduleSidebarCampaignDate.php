<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleSidebarCampaignDate extends Model
{
    protected $table = 'schedule_sidebar_campaign_dates';

    protected $fillable = [
        'schedule_sidebar_campaign_id',
        'schedule_date',
        'quantity',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    public function campaign()
    {
        return $this->belongsTo(ScheduleSidebarCampaign::class, 'schedule_sidebar_campaign_id');
    }
}
