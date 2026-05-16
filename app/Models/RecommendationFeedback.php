<?php

namespace App\Models;

use App\Enums\FeedbackAction;
use App\Enums\FeedbackSourceType;
use App\Enums\MealTimeSlot;
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
        'source_type'    => FeedbackSourceType::class,
        'action'         => FeedbackAction::class,
        'meal_time_slot' => MealTimeSlot::class,
        'calories'       => 'decimal:2',
        'protein'        => 'decimal:2',
        'carbs'          => 'decimal:2',
        'fats'           => 'decimal:2',
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
