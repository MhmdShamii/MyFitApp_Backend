<?php

namespace App\Models;

use App\Enums\UserActivityLevels;
use App\Enums\UserDietaryPreferences;
use App\Enums\UserGender;
use App\Enums\UserGoal;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $table = 'user_profile';

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'weight_kg',
        'height_cm',
        'body_fat_pct',
        'activity_level',
        'goal',
        'dietary_preferences',
        'daily_calorie_target',
        'daily_protein_g',
        'daily_carbs_g',
        'daily_fat_g',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'weight_kg' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'gender' => UserGender::class,
        'activity_level' => UserActivityLevels::class,
        'goal' => UserGoal::class,
        'dietary_preferences' => UserDietaryPreferences::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function memories()
    {
        return $this->hasMany(UserMemorie::class, 'profile_id');
    }
}
