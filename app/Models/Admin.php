<?php

namespace App\Models;

use App\Models\Admin\Article;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $guard = 'admin';

    // Role Constants
    const SUPER_ADMIN = 0;
    const ADMIN = 1;
    const MEMBER = 2;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function setPasswordAttribute($value)
    {
        if (strlen($value) === 60 && preg_match('/^\$2y\$/', $value)) {
            $this->attributes['password'] = $value;
        } else {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    protected static function booted()
    {
        static::creating(function ($admin) {
            $admin->slug = static::generateUniqueSlug($admin->name);
        });

        static::updating(function ($admin) {
            // Only regenerate slug if name is changed
            if ($admin->isDirty('name')) {
                $admin->slug = static::generateUniqueSlug($admin->name, $admin->id);
            }
        });
    }

    // Generate unique slug
    protected static function generateUniqueSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);
        $original = $slug;

        $count = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $original . '-' . $count;
            $count++;
        }

        return $slug;
    }

    // ** Role Helper Methods ** //

    /**
     * Check if the admin is a Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->type === self::SUPER_ADMIN;
    }

    /**
     * Check if the admin is an Admin
     */
    public function isAdmin(): bool
    {
        return (int) $this->type === self::ADMIN;
    }

    /**
     * Check if the admin is a Member
     */
    public function isMember(): bool
    {
        return (int) $this->type === self::MEMBER;
    }

    /**
     * Check if the admin can create campaigns.
     * Members can only add articles; Super Admin and Admin can create campaigns.
     */
    public function canCreateCampaigns(): bool
    {
        return in_array((int) $this->type, [self::SUPER_ADMIN, self::ADMIN], true);
    }

    /**
     * Check if the admin has a specific role
     */
    public function hasRole(int $roleType): bool
    {
        return (int) $this->type === $roleType;
    }

    /**
     * Get role name
     */
    public function getRoleName(): string
    {
        return match ((int) $this->type) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::MEMBER => 'Member',
            default => 'Unknown',
        };
    }

    /**
     * Get role relationship
     */
    public function role()
    {
        return $this->belongsTo(Roles::class, 'type', 'role_id');
    }

    // ** Relation With Domain Category ** //

    public function DomainCategory()
    {
        return $this->hasMany(DomainCategory::class);
    }

    // ** Relation With Article Category ** //

    public function ArticleCategory()
    {
        return $this->hasMany(ArticleCategory::class);
    }

    // ** Relation With Articles ** //

    public function articles()
    {
        return $this->hasMany(Article::class, 'admin_id');
    }

    // ** Relation With Campaigns ** //

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'admin_id');
    }

    // ** Relation With Domains ** //

    public function domains()
    {
        return $this->hasMany(Domain::class, 'admin_id');
    }
}
