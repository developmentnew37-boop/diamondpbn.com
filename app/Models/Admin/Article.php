<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Models\Admin;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleLanguage;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    /* --------------------------------
     | Table (explicit is better)
     |--------------------------------*/
    protected $table = 'articles';


    /* --------------------------------
     | Constants (Clean code)
     |--------------------------------*/
    const TYPE_MANUAL = 0;
    const TYPE_FILE   = 1;
    const TYPE_AI     = 2;

    const STATUS_UNUSED   = 0;
    const STATUS_USED     = 1;
    const STATUS_ARCHIVED = 2;


    /* --------------------------------
     | Mass Assignment
     |--------------------------------*/
    //  'source_file','meta_title','meta_description','meta_keywords','is_ai_processed','search_text',

    protected $fillable = [
        'name',
        'slug',
        'description',
        'search_text',
        'article_category_id',
        'article_language_id',
        'type',
        'status',
        'lock_at',
        'expires_at',
        'admin_id',
    ];


    /* --------------------------------
     | Model Events
     |--------------------------------*/
    protected static function booted()
    {
        static::creating(function ($article) {

            if (empty($article->slug)) {
                $article->slug = static::generateUniqueSlug($article->name);
            }

            $article->search_text = static::makeSearchText(
                $article->name,
                $article->description
            );
        });

        static::updating(function ($article) {

            // ✅ ALWAYS regenerate slug if name changed
            if ($article->isDirty('name')) {
                $article->slug = static::generateUniqueSlug(
                    $article->name,
                    $article->id
                );
            }

            if ($article->isDirty(['name', 'description'])) {
                $article->search_text = static::makeSearchText(
                    $article->name,
                    $article->description
                );
            }
        });
    }

    /* --------------------------------
     | Slug Helpers
     |--------------------------------*/

    protected static function generateUniqueSlug(string $title, $ignoreId = null): string
    {
        $cleanTitle = static::removeEmojis($title);

        $baseSlug = Str::slug($cleanTitle);

        if ($baseSlug === '') {
            $baseSlug = 'article';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (static::slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected static function slugExists(string $slug, $ignoreId = null): bool
    {
        return static::where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected static function removeEmojis(string $text): string
    {
        return preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $text);
    }
    /* --------------------------------
     | Search Helper
     |--------------------------------*/
    protected static function makeSearchText(string $title, ?string $html = null): string
    {
        $text = $title . ' ' . ($html ?? '');

        return Str::of(strip_tags($text))
            ->replaceMatches('/\s+/', ' ')
            ->lower()
            ->trim();
    }


    /* --------------------------------
     | Relationships
     |--------------------------------*/
    public function category()
    {
        return $this->belongsTo(
            ArticleCategory::class,
            'article_category_id'
        );
    }

    public function language()
    {
        return $this->belongsTo(
            ArticleLanguage::class,
            'article_language_id'
        );
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /* --------------------------------
     | Scopes (Useful for big data)
     |--------------------------------*/
    public function scopeUnused($query)
    {
        return $query->where('status', 0);
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 1);
    }

    /* --------------------------------
     | Casting
     |--------------------------------*/
    protected $casts = [
        'type'       => 'integer',
        'status'     => 'integer',
        'lock_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];


    /* --------------------------------
     | Many to many relationship    
     |--------------------------------*/

    public function articleSet()
    {
        return $this->belongsToMany(ArticleSet::class);
    }

    public function articleSets()
    {
        return $this->belongsToMany(
            ArticleSet::class,
            'article_set_items',
            'article_id',
            'article_set_id'
        );
    }
}
