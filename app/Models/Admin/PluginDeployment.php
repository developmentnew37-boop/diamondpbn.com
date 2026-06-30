<?php

namespace App\Models\Admin;

use App\Models\Admin as AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PluginDeployment extends Model
{
    public const OPERATIONS = [
        'install',
        'update',
        'update_if_older',
        'activate',
        'deactivate',
        'delete',
    ];

    protected $fillable = [
        'uuid',
        'admin_id',
        'plugin_package_id',
        'operation',
        'source',
        'domain_category_id',
        'status',
        'phase',
        'total_count',
        'processed_count',
        'success_count',
        'failed_count',
        'skipped_count',
        'activate_after',
        'skip_if_same_version',
        'status_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'activate_after' => 'boolean',
        'skip_if_same_version' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PluginDeployment $deployment) {
            if (empty($deployment->uuid)) {
                $deployment->uuid = (string) Str::uuid();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }

    public function pluginPackage(): BelongsTo
    {
        return $this->belongsTo(PluginPackage::class);
    }

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PluginDeploymentItem::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    public function progressPercent(): int
    {
        $total = max(1, (int) $this->total_count);

        if ($this->isFinished()) {
            return 100;
        }

        return (int) min(100, round(((int) $this->processed_count / $total) * 100));
    }

    public function operationLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->operation));
    }

    public function statusBadgeClass(): string
    {
        if ($this->status === 'completed' && $this->failed_count > 0) {
            return 'pm-deploy-status-completed-warn';
        }

        return match ($this->status) {
            'completed' => 'pm-deploy-status-completed',
            'running' => 'pm-deploy-status-running',
            'queued' => 'pm-deploy-status-queued',
            'cancelled' => 'pm-deploy-status-cancelled',
            default => 'pm-deploy-status-queued',
        };
    }

    public function hasFailures(): bool
    {
        return $this->failed_count > 0 && $this->status === 'completed';
    }
}
