<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\CampaignPost;

class CampaignDomain extends Model
{
    protected $table = 'campaign_domains';

    protected $fillable = [
        'campaign_id',
        'domain_id',
        'sort_order'
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function campaignPosts()
    {
        return $this->hasMany(CampaignPost::class, 'campaign_domain_id');
    }
}
