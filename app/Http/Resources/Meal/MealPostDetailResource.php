<?php

namespace App\Http\Resources\Meal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MealPostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user    = $this->userProfile?->user;
        $authUser = Auth::user();

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'visibility'  => $this->visibility,
            'image_url'   => $this->image_url,
            'servings'    => $this->servings,

            'macros' => [
                'calories' => (float) $this->mealMacro?->calories,
                'protein'  => (float) $this->mealMacro?->protein,
                'carbs'    => (float) $this->mealMacro?->carbs,
                'fats'     => (float) $this->mealMacro?->fats,
                'fiber'    => (float) $this->mealMacro?->fiber,
            ],

            'ingredients' => $this->ingredients->map(fn($ingredient) => [
                'id'      => $ingredient->id,
                'name_en' => $ingredient->name_en,
                'name_ar' => $ingredient->name_ar,
                'portion' => $ingredient->pivot->portion,
                'unit'    => $ingredient->pivot->unit,
            ]),

            'preparation_steps' => $this->preparationSteps->map(fn($step) => [
                'step_number' => $step->step_number,
                'description' => $step->description,
            ]),

            'engagement' => [
                'likes_count'    => $this->likes_count,
                'relogs_count'   => $this->relogs_count,
                'comments_count' => $this->comments_count,
                'is_liked'       => $authUser
                    ? $this->likes()->where('user_id', $authUser->id)->exists()
                    : false,
            ],

            'author' => [
                'id'         => $user?->id,
                'first_name' => $user?->first_name,
                'last_name'  => $user?->last_name,
                'avatar'     => $this->resolveAvatarUrl($user?->image),
                'role'       => $user?->role,
            ],
        ];
    }

    private function resolveAvatarUrl(?string $image): string
    {
        if (!$image || $image === 'default.png') {
            return Storage::disk('s3')->url('avatars/default.png');
        }

        if (str_starts_with($image, 'http')) {
            return $image;
        }

        return Storage::disk('s3')->url($image);
    }
}
