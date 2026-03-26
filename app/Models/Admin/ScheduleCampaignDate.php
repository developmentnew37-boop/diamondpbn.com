<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ScheduleCampaignDate extends Model
{
    protected $table = 'schedule_campaign_dates';

    protected $fillable = [
        'schedule_campaign_id',
        'schedule_date',
        'quantity',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    public function campaign()
    {
        return $this->belongsTo(ScheduleCampaign::class, 'schedule_campaign_id');
    }
}
