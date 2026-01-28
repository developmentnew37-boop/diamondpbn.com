<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminOtp extends Model
{
    //
    protected $fillable = ['admin_id', 'otp', 'expires_at'];
    protected $dates = ['expires_at'];
}
