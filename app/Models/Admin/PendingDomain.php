<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class PendingDomain extends Model
{
    use HasFactory;

    public const SIDEBAR_COUNT_CACHE_KEY = 'pending_domain_sidebar_counts';

    protected $fillable = [
        'domain_name',
        'normalized_domain_name',
        'api_key',
        'viewed',
        'webhook_secret_id',
        'status',
        'notes',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'viewed' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PendingDomain $pending) {
            $pending->normalized_domain_name = normalizeDomainName($pending->domain_name);
        });

        static::saved(fn () => static::flushSidebarCountCache());
        static::deleted(fn () => static::flushSidebarCountCache());
    }

    /**
     * @return array{unviewed: int, transfer: int}
     */
    public static function cachedSidebarCounts(): array
    {
        return Cache::remember(static::SIDEBAR_COUNT_CACHE_KEY, 60, function () {
            return [
                'unviewed' => static::query()->where('status', 'pending')->where('viewed', false)->count(),
                'transfer' => static::query()->where('status', 'pending')->count(),
            ];
        });
    }

    public static function flushSidebarCountCache(): void
    {
        Cache::forget(static::SIDEBAR_COUNT_CACHE_KEY);
    }

    public function webhookSecret(): BelongsTo
    {
        return $this->belongsTo(WebhookSecret::class);
    }

    public function markAsViewed(): void
    {
        if (! $this->viewed) {
            $this->update(['viewed' => true]);
        }
    }

    public function reject(?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function getNormalizedNameAttribute(): string
    {
        return normalizeDomainName($this->domain_name);
    }

    public function getDomainUrlAttribute(): string
    {
        return toDomainUrl($this->domain_name);
    }

    public function getExtensionAttribute(): string
    {
        return extractDomainExtension($this->domain_name);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnviewed($query)
    {
        return $query->where('viewed', false);
    }
}
