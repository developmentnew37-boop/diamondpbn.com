<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DomainSet extends Model
{
    //
    protected $guarded = [];

    protected static function booted()
    {
        // On create
        static::creating(function ($domainSet) {
            $domainSet->slug = static::generateUniqueSlug($domainSet->name);
        });

        // On update (only if name changed)
        static::updating(function ($domainSet) {
            if ($domainSet->isDirty('name')) {
                $domainSet->slug = static::generateUniqueSlug($domainSet->name, $domainSet->id);
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

    /**
     * Get the domain category that owns this domain set.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domainCategory()
    {
        return $this->belongsTo(DomainCategory::class);
    }

    protected $casts = [
        'domains' => 'array',  // auto json_encode / json_decode
    ];
}
