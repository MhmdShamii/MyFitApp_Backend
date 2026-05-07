<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformDailyStat extends Model
{
    protected $primaryKey = 'date';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['date', 'new_users', 'meals_logged'];
}
