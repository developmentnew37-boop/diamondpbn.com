<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookSecret extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'secret',
        'is_active',
        'last_used_at',
        'secret_rotated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'secret_rotated_at' => 'datetime',
    ];

    /**
     * Generate a new secure webhook secret token and persist it.
     */
    public function rotateSecret(): string
    {
        $newSecret = static::generateSecret();

        $this->update([
            'secret' => $newSecret,
            'secret_rotated_at' => now(),
        ]);

        return $newSecret;
    }

    /**
     * Generate a new secure webhook secret
     */
    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * All domains ever submitted with this secret (includes approved/rejected).
     */
    public function pendingDomains(): HasMany
    {
        return $this->hasMany(PendingDomain::class);
    }

    /**
     * Domains still awaiting transfer or rejection (status = pending).
     */
    public function awaitingPendingDomains(): HasMany
    {
        return $this->hasMany(PendingDomain::class)->where('status', 'pending');
    }

    /**
     * Mark this secret as used now
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Validate a secret token against this record
     */
    public function validateSecret(string $providedSecret): bool
    {
        return $this->is_active && hash_equals($this->secret, $providedSecret);
    }
}
