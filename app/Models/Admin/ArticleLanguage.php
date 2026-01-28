<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin;
use Illuminate\Support\Str;
use App\Models\Admin\Article;

class ArticleLanguage extends Model
{
    //
    protected $guarded = [];

    // Auto-generate slug when creating
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lang) {
            $lang->slug = Str::slug($lang->name);
        });

        static::updating(function ($lang) {
            if ($lang->isDirty('name')) {
                $lang->slug = Str::slug($lang->name);
            }
        });
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function Article()
    {
        return $this->hasMany(Article::class);
    }

    public function ArticleSet(){
        return $this->hasMany(ArticleSet::class);
    }
}
