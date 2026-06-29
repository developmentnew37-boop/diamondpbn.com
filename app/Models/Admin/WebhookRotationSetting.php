<?php

namespace App\Models\Admin;

use App\Models\Admin as AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookRotationSetting extends Model
{
    protected $fillable = [
        'rotation_hours',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'rotation_hours' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'rotation_hours' => max(0, (int) config('webhook.default_rotation_hours', 8)),
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->rotation_hours > 0;
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    /**
     * @return array<int, int>
     */
    public static function selectableHourOptions(): array
    {
        return [0, 1, 2, 3, 4, 5, 6, 8, 12, 24, 48, 72, 168];
    }
}
