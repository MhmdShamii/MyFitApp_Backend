<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachApplicationDocument extends Model
{
    protected $fillable = [
        'coach_application_id',
        'file_path',
        'original_name',
        'type',
    ];

    public function application()
    {
        return $this->belongsTo(CoachApplication::class, 'coach_application_id');
    }
}
