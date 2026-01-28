<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPasswordReset extends Model
{
    //
    protected $fillable = [
        'email',
        'token',
        'created_at'
    ];

    public $timestamps = false; // created_at only
    public $incrementing = false;        // no auto increment
    protected $primaryKey = 'email';     // use email as primary key
    protected $keyType = 'string';       // email is string
}
