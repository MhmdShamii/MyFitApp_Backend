<?php

namespace App\Http\Resources\Social;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FeedPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->userProfile?->user;

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'image_url'   => $this->image_url,
            'servings'    => $this->servings,
            'posted_at'   => $this->confirmed_at->toISOString(),

            'author' => [
                'id'           => $author?->id,
                'first_name'   => $author?->first_name,
                'last_name'    => $author?->last_name,
                'avatar'       => $this->resolveAvatarUrl($author?->image),
                'role'         => $author?->role,
                'is_following' => (bool) $this->viewer_follows_author,
            ],

            'macros' => [
                'calories' => (float) $this->mealMacro?->calories,
                'protein'  => (float) $this->mealMacro?->protein,
                'carbs'    => (float) $this->mealMacro?->carbs,
                'fats'     => (float) $this->mealMacro?->fats,
                'fiber'    => (float) $this->mealMacro?->fiber,
            ],

            'ingredients' => $this->ingredients->map(fn($i) => [
                'id'      => $i->id,
                'name_en' => $i->name_en,
                'name_ar' => $i->name_ar,
                'portion' => $i->pivot->portion,
                'unit'    => $i->pivot->unit,
            ]),

            'preparation_steps' => $this->preparationSteps->map(fn($s) => [
                'step_number' => $s->step_number,
                'description' => $s->description,
            ]),

            'engagement' => [
                'likes_count'    => $this->likes_count,
                'comments_count' => $this->comments_count,
                'relogs_count'   => $this->relogs_count,
                'is_liked'       => $this->relationLoaded('likes') && $this->likes->isNotEmpty(),
            ],

            'first_comment' => $this->when(
                $this->relationLoaded('firstComment') && $this->firstComment !== null,
                fn() => [
                    'id'         => $this->firstComment->id,
                    'body'       => $this->firstComment->body,
                    'created_at' => $this->firstComment->created_at->toISOString(),
                    'author'     => [
                        'id'         => $this->firstComment->user->id,
                        'first_name' => $this->firstComment->user->first_name,
                        'last_name'  => $this->firstComment->user->last_name,
                        'avatar'     => $this->resolveAvatarUrl($this->firstComment->user->image),
                    ],
                ]
            ),
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
