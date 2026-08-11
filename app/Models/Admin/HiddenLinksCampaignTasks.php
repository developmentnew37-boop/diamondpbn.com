<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class HiddenLinksCampaignTasks extends Model
{
    protected $table = 'hidden_links_campaigns_tasks';

    protected $fillable = [
        'hidden_links_campaigns_id',
        'hidden_links_campaigns_domain_id',
        'hidden_links_campaigns_link_id',
        'status',
        'links_payload',
        'remote_id',
        'remote_url',
        'http_status',
        'remote_response',
        'published_at',
        'attempt_count',
        'max_attempts',
        'last_error',
        'next_retry_at',
        'started_at',
        'finished_at',
        'locked_at',
        'lock_token',
        'locked_until',
        'content_updated_at',
        'dispatch_generation',
    ];

    protected $casts = [
        'links_payload' => 'array',
        'remote_response' => 'array',

        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'http_status' => 'integer',

        'next_retry_at' => 'datetime',
        'published_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'locked_at' => 'datetime',
        'locked_until' => 'datetime',
        'content_updated_at' => 'datetime',
    ];

    // =========================
    // Relationships
    // =========================

    public function campaign()
    {
        return $this->belongsTo(HiddenLinksCampaign::class, 'hidden_links_campaigns_id');
    }

    public function domainRow()
    {
        return $this->belongsTo(HiddenLinksCampaignDomains::class, 'hidden_links_campaigns_domain_id');
    }

    public function linkRow()
    {
        return $this->belongsTo(HiddenLinksCampaignLinks::class, 'hidden_links_campaigns_link_id');
    }

    // Quick access: $task->domainRow->domain
    public function domain(): ?Domain
    {
        return $this->domainRow?->domain;
    }

    // =========================
    // Scopes for Worker pickup
    // =========================

    public function scopeDue($q)
    {
        return $q->whereIn('status', ['queued', 'publishing'])
            ->where(function ($qq) {
                $qq->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }
}
