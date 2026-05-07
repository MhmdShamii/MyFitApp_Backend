<?php

namespace App\Models;

use App\Enums\CoachApplicationStatus;
use Illuminate\Database\Eloquent\Model;

class CoachApplication extends Model
{
    protected $fillable = [
        'user_id',
        'description',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status'      => CoachApplicationStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(CoachApplicationDocument::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
