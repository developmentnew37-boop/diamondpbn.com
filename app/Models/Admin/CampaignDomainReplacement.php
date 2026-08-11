<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

class CampaignDomainReplacement extends Model
{
    protected static function booted(): void
    {
        static::updating(function (CampaignDomainReplacement $replacement) {
            if (in_array($replacement->getOriginal('state'), ['failed', 'completed'], true)) {
                throw new \LogicException('Finalized campaign domain replacement audits are immutable.');
            }

            if (in_array($replacement->getOriginal('state'), ['dispatch_pending', 'dispatching', 'dispatch_failed'], true)
                && array_diff(array_keys($replacement->getDirty()), ['state', 'error', 'updated_at']) !== []) {
                throw new \LogicException('Committed campaign domain replacement audit details are immutable.');
            }
        });

        static::deleting(function () {
            throw new \LogicException('Campaign domain replacement audits cannot be deleted.');
        });
    }

    protected $fillable = [
        'request_uuid',
        'campaign_id',
        'campaign_post_id',
        'campaign_domain_id',
        'old_domain_id',
        'new_domain_id',
        'old_hostname',
        'new_hostname',
        'admin_id',
        'reason',
        'previous_status',
        'result_status',
        'health_snapshot',
        'dispatch_generation',
        'state',
        'error',
    ];

    protected $casts = [
        'health_snapshot' => 'array',
        'dispatch_generation' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignPost()
    {
        return $this->belongsTo(CampaignPost::class);
    }

    public function campaignDomain()
    {
        return $this->belongsTo(CampaignDomain::class);
    }

    public function oldDomain()
    {
        return $this->belongsTo(Domain::class, 'old_domain_id');
    }

    public function newDomain()
    {
        return $this->belongsTo(Domain::class, 'new_domain_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
