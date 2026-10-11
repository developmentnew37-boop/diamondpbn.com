<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LocalClientInstallment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'local_client_id',
        'amount',
        'currency',
        'source',
        'note',
        'admin_id',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function localClient(): BelongsTo
    {
        return $this->belongsTo(LocalClient::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LocalClientInstallmentAllocation::class, 'installment_id');
    }

    /**
     * @param  Builder<LocalClientInstallment>  $query
     * @param  array{client_id?: int|null, admin_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<LocalClientInstallment>
     */
    public function scopeForLedger(Builder $query, array $filters): Builder
    {
        if (! empty($filters['client_id'])) {
            $query->where('local_client_id', (int) $filters['client_id']);
        }

        if (! empty($filters['admin_id'])) {
            $query->where('admin_id', (int) $filters['admin_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query;
    }

    public function sourceLabel(): string
    {
        return $this->source === 'reallocation' ? 'Billing adjustment' : 'Payment';
    }

    public function allocationSummary(): string
    {
        return $this->allocations
            ->map(function (LocalClientInstallmentAllocation $allocation) {
                $name = $allocation->campaign_no ?: 'Campaign';
                $status = $allocation->new_status === 'paid' ? 'paid' : 'semi paid';

                return $name.' '.$status;
            })
            ->implode(', ');
    }
}
