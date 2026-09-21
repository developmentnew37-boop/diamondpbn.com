<?php

namespace App\Models\Admin;

use App\Models\Admin as AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSetting extends Model
{
    protected $fillable = [
        'require_unique_titles',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'require_unique_titles' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'require_unique_titles' => true,
        ]);
    }

    public function requiresUniqueTitles(): bool
    {
        return (bool) $this->require_unique_titles;
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }
}
