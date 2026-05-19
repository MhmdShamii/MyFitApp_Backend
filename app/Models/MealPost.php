<?php

namespace App\Models;

use App\Enums\MealVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MealPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_profile_id',
        'fingerprint',
        'name',
        'description',
        'visibility',
        'image_url',
        'confirmed_at',
        'servings',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'servings'     => 'integer',
        'visibility'   => MealVisibility::class,
    ];

    // Relations
    public function userProfile()
    {
        return $this->belongsTo(UserProfile::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'meal_ingredients')
            ->withPivot('portion', 'unit');
    }

    public function mealMacro()
    {
        return $this->belongsTo(MealMacro::class, 'fingerprint', 'fingerprint');
    }

    public function preparationSteps(): HasMany
    {
        return $this->hasMany(MealPreparationStep::class)->orderBy('step_number');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meal_post_likes', 'meal_post_id', 'user_id');
    }

    public function saves(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meal_post_saves', 'meal_post_id', 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MealPostComment::class)->whereNull('parent_id')->oldest();
    }
}
