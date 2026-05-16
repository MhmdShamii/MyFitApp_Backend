<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationFeedback extends Model
{
    protected $table = 'recommendation_feedback';

    protected $fillable = [
        'profile_id',
        'meal_title',
        'meal_post_id',
        'source_type',
        'action',
        'meal_time_slot',
        'logged_hour',
        'calories',
        'protein',
        'carbs',
        'fats',
        'embedding',
    ];

    protected $casts = [
        'calories' => 'float',
        'protein'  => 'float',
        'carbs'    => 'float',
        'fats'     => 'float',
    ];

    public function profile()
    {
        return $this->belongsTo(UserProfile::class, 'profile_id');
    }

    public function mealPost()
    {
        return $this->belongsTo(MealPost::class, 'meal_post_id');
    }
}
