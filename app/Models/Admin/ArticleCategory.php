<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Admin;
class ArticleCategory extends Model
{
    //

    protected $guarded = [];

    protected static function booted()
    {
        // On create
        static::creating(function ($articleCategory) {
            $articleCategory->slug = static::generateUniqueSlug($articleCategory->name);
        });

        // On update (only if name changed)
        static::updating(function ($articleCategory) {
            if ($articleCategory->isDirty('name')) {
                $articleCategory->slug = static::generateUniqueSlug($articleCategory->name, $articleCategory->id);
            }
        });
    }

    // Function to make unique slug
    protected static function generateUniqueSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        // Ensure uniqueness
        while (
            static::where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }



    // ==============================
    // 🔗 RELATION: Parent Category
    // ==============================
    public function parent()
    {
        return $this->belongsTo(ArticleCategory::class, 'parent_id');
    }

    // ==============================
    // 🔗 RELATION: Sub Categories (Children)
    // ==============================
    public function children()
    {
        return $this->hasMany(ArticleCategory::class, 'parent_id');
    }
    // ==============================
    // 🔗 RELATION: Admin (Children)
    // ==============================
    public function Admin()
    {
        return $this->belongsTo(Admin::class);
    }

     // ==============================
    // 🔗 RELATION: Article 
    // ==============================

    public function Article(){
        return $this->hasMany(Article::class);
    }
}
