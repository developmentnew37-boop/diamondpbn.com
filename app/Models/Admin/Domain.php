<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    //

    protected $table = 'domains';

    protected $guarded = [];

    protected $hidden = []; // or remove attribute completely

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }
}
