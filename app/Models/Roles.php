<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    protected $fillable = ['role_id', 'role'];

    // Role Constants (matching admin.type values)
    const SUPER_ADMIN = 0;
    const ADMIN = 1;
    const MEMBER = 2;

    /**
     * Get all admins with this role
     */
    public function admins()
    {
        return $this->hasMany(Admin::class, 'type', 'role_id');
    }

    /**
     * Get role name by ID
     */
    public static function getRoleName(int $roleId): string
    {
        return match ($roleId) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::MEMBER => 'Member',
            default => 'Unknown',
        };
    }
}
