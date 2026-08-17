<?php

namespace App\Models;

use App\Models\Admin\AdminFeaturePermission;
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
            static::assertSingleSuperAdmin($admin);
        });

        static::updating(function ($admin) {
            // Only regenerate slug if name is changed
            if ($admin->isDirty('name')) {
                $admin->slug = static::generateUniqueSlug($admin->name, $admin->id);
            }

            if ($admin->isDirty('type')) {
                static::assertSingleSuperAdmin($admin);
            }
        });
    }

    /**
     * Enforce a single Super Admin account.
     * - Nobody can create another Super Admin if one already exists.
     * - Nobody can promote a user to Super Admin if one already exists.
     * - The existing Super Admin cannot be demoted (system must keep one).
     */
    protected static function assertSingleSuperAdmin(self $admin): void
    {
        $newType = (int) $admin->type;
        $originalType = $admin->exists ? (int) $admin->getOriginal('type') : null;

        if ($originalType === self::SUPER_ADMIN && $newType !== self::SUPER_ADMIN) {
            throw new \RuntimeException('The Super Admin role cannot be removed or demoted.');
        }

        if ($newType !== self::SUPER_ADMIN) {
            return;
        }

        $query = static::query()->where('type', self::SUPER_ADMIN);
        if ($admin->exists) {
            $query->where('id', '!=', $admin->id);
        }

        if ($query->exists()) {
            throw new \RuntimeException('Only one Super Admin is allowed.');
        }
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

    public function featurePermissions(): HasMany
    {
        return $this->hasMany(AdminFeaturePermission::class, 'admin_id');
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->featurePermissions()
            ->pluck('permission')
            ->map(fn ($key) => (string) $key)
            ->values()
            ->all();
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Feature permissions are Admin-only; Members cannot hold them.
        if (! $this->isAdmin()) {
            return false;
        }

        if (! array_key_exists($key, config('admin_permissions', []))) {
            return false;
        }

        if ($this->relationLoaded('featurePermissions')) {
            return $this->featurePermissions->contains('permission', $key);
        }

        return $this->featurePermissions()
            ->where('permission', $key)
            ->exists();
    }

    /**
     * @param  list<string>|array<int, string>  $keys
     */
    public function syncFeaturePermissions(array $keys): void
    {
        // Only Admin role may hold feature grants. Super Admin is implicit; Members get none.
        if ($this->isSuperAdmin() || ! $this->isAdmin()) {
            $this->featurePermissions()->delete();
            $this->unsetRelation('featurePermissions');

            return;
        }

        $allowed = array_keys(config('admin_permissions', []));
        $normalized = array_values(array_unique(array_filter(
            array_map('strval', $keys),
            fn (string $key) => in_array($key, $allowed, true)
        )));

        $this->featurePermissions()->delete();

        if ($normalized !== []) {
            $now = now();
            $this->featurePermissions()->insert(array_map(
                fn (string $permission) => [
                    'admin_id' => $this->id,
                    'permission' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $normalized
            ));
        }

        // Avoid stale in-memory relation after delete/insert.
        $this->unsetRelation('featurePermissions');
    }

    public function canManageAllTrashedArticles(): bool
    {
        return $this->hasPermission('articles.manage_all_trashed');
    }

    public function canManageLocalClients(): bool
    {
        return $this->hasPermission('local_clients.manage');
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
