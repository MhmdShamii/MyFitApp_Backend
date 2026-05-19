<?php

namespace App\Http\Resources\Meal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'image_url' => $this->image_url,

            'macros' => [
                'calories' => (float) $this->mealMacro?->calories,
                'protein'  => (float) $this->mealMacro?->protein,
                'carbs'    => (float) $this->mealMacro?->carbs,
                'fats'     => (float) $this->mealMacro?->fats,
            ],

            'engagement' => [
                'likes_count'    => $this->likes_count,
                'relogs_count'   => $this->relogs_count,
                'comments_count' => $this->comments_count,
                'is_liked'       => $this->relationLoaded('likes') && $this->likes->isNotEmpty(),
                'is_saved'       => $this->relationLoaded('saves') && $this->saves->isNotEmpty(),
            ],
        ];
    }
}
