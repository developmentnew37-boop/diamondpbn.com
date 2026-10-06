<?php

namespace App\Models\Admin;

use App\Models\Admin as AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PluginInventoryReport extends Model
{
    protected $fillable = [
        'uuid',
        'admin_id',
        'source',
        'domain_category_id',
        'status',
        'total_count',
        'processed_count',
        'success_count',
        'failed_count',
        'file_path',
        'status_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PluginInventoryReport $report) {
            if (empty($report->uuid)) {
                $report->uuid = (string) Str::uuid();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class);
    }

    public function scopeVisibleTo(Builder $query, AdminUser $admin): Builder
    {
        if ($admin->isSuperAdmin()) {
            return $query;
        }

        return $query->where('admin_id', $admin->id);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function progressPercent(): int
    {
        $total = max(1, (int) $this->total_count);

        if ($this->status === 'completed') {
            return 100;
        }

        return (int) min(100, round(((int) $this->processed_count / $total) * 100));
    }
}
