<?php

namespace App\Models\Admin;

use App\Models\Admin as AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DomainStatusCheck extends Model
{
    protected $fillable = [
        'uuid',
        'admin_id',
        'source',
        'status',
        'phase',
        'total_count',
        'processed_count',
        'connected_count',
        'disconnected_count',
        'in_inventory_count',
        'inventory_updated_count',
        'update_inventory',
        'use_authenticated_check',
        'domain_category_id',
        'status_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'update_inventory' => 'boolean',
        'use_authenticated_check' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DomainStatusCheck $check) {
            if (empty($check->uuid)) {
                $check->uuid = (string) Str::uuid();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DomainStatusCheckItem::class);
    }

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
