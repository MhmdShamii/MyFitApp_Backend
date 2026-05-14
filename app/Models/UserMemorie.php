<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMemorie extends Model
{
    protected $fillable = [
        'profile_id',
        'key',
        'value',
    ];

    public function userProfile()
    {
        return $this->belongsTo(UserProfile::class, 'profile_id');
    }
}
