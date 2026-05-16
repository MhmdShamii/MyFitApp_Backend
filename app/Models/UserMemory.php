<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMemory extends Model
{
    protected $table = 'user_assistant_memory';

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
