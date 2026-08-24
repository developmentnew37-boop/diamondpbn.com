<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalClientRateList extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'is_active',
        'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(LocalClientRateListPrice::class, 'rate_list_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(LocalClient::class, 'rate_list_id');
    }
}
