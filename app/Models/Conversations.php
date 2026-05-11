<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversations extends Model
{
    protected $fillable = [
        'profile_id',
        'title',
        'summary',
        'last_active_at',
    ];

    public function messages()
    {
        return $this->hasMany(ConversationMessages::class, 'conversation_id');
    }
}
