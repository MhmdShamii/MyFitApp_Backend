<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMessages extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tokens_used',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversations::class, 'conversation_id');
    }
}
