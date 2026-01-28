<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class HiddenLinksCampaignDomains extends Model
{
    protected $table = 'hidden_links_campaigns_domains';

    protected $fillable = [
        'hidden_links_campaigns_id',
        'domain_id',
    ];

    public function campaign()
    {
        return $this->belongsTo(HiddenLinksCampaign::class, 'hidden_links_campaigns_id');
    }

    public function domain()
    {
        // Domain model assumed: App\Models\Admin\Domain or App\Models\Domain
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function tasks()
    {
        return $this->hasMany(HiddenLinksCampaignTasks::class, 'hidden_links_campaigns_domain_id');
    }
}
