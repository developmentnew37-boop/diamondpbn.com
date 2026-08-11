<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LocalClient extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'notes',
        'default_currency',
        'billing_report_token',
        'is_active',
        'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $client) {
            if (empty($client->billing_report_token)) {
                $client->billing_report_token = Str::random(64);
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function categoryPrices(): HasMany
    {
        return $this->hasMany(LocalClientDomainCategoryPrice::class);
    }

    public function billLines(): HasMany
    {
        return $this->hasMany(LocalClientBillLine::class);
    }

    public function billingPeriods(): HasMany
    {
        return $this->hasMany(LocalClientBillingPeriod::class);
    }

    public function regenerateBillingReportToken(): void
    {
        $this->billing_report_token = Str::random(64);
        $this->save();
    }
}
