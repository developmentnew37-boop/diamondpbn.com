<?php

namespace App\Models\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DomainCategory extends Model
{
    //

    protected $table = 'domain_categories';


    protected $guarded = [];

    protected static function booted()
    {
        // When creating new category
        static::creating(function ($category) {
            $category->slug = static::makeUniqueSlug($category->name);
        });

        // When updating category name
        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = static::makeUniqueSlug($category->name, $category->id);
            }
        });
    }

    // Create unique slug
    protected static function makeUniqueSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (
            static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    // ** every category belongs to an users ** //

    public function Admin(){
        return $this->belongsTo(Admin::class);
    }

    public function DomainSet(){
        return $this->hasMany(DomainSet::class);
    }

    public function domains(){
        return $this->hasMany(Domain::class);
    }
}
