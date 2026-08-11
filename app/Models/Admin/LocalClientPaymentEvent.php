<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalClientPaymentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'billable_type',
        'billable_id',
        'local_client_id',
        'campaign_no',
        'old_status',
        'new_status',
        'note',
        'admin_id',
        'created_at',
    ];

    protected $casts = [
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
}
