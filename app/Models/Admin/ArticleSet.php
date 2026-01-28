<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Admin;
class ArticleSet extends Model
{
    //
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($articleSet) {

            // Generate slug only if not provided
            if (empty($articleSet->slug)) {
                $articleSet->slug = static::generateUniqueSlug($articleSet->name);
            }
        });
    }

    /**
     * Generate unique slug
     */
    protected static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'article-set';
        $slug = $baseSlug;
        $counter = 1;

        // Check uniqueness
        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /* for race condition */

    public function language(){
        return $this->belongsTo(ArticleLanguage::class,'article_language_id');
    }
    

    /*many to many to relationship*/

        public function articles()
        {
            return $this->belongsToMany(Article::class,'article_set_items', 'article_set_id', 'article_id');
        }

        // ==============================
    // 🔗 RELATION: Admin (Children)
    // ==============================
    public function Admin()
    {
        return $this->belongsTo(Admin::class);
    }

}
