<?php

namespace App\Models\Admin;

use App\Models\Admin as AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PluginPackage extends Model
{
    protected $fillable = [
        'uuid',
        'admin_id',
        'slug',
        'name',
        'version',
        'original_filename',
        'storage_path',
        'file_size_bytes',
        'checksum_sha256',
        'is_diamond_pbn_agent',
        'notes',
    ];

    protected $casts = [
        'is_diamond_pbn_agent' => 'boolean',
        'file_size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PluginPackage $package) {
            if (empty($package->uuid)) {
                $package->uuid = (string) Str::uuid();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(PluginDeployment::class);
    }

    public function displayLabel(): string
    {
        return $this->name.' v'.$this->version.' ('.$this->slug.')';
    }
}
