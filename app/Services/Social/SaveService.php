<?php

namespace App\Services\Social;

use App\Models\MealPost;
use App\Models\User;

class SaveService
{
    public function save(User $user, MealPost $meal): void
    {
        if ($meal->saves()->where('user_id', $user->id)->exists()) {
            throw new \RuntimeException('You have already saved this meal.');
        }

        $meal->saves()->attach($user->id);
    }

    public function unsave(User $user, MealPost $meal): void
    {
        if (!$meal->saves()->where('user_id', $user->id)->exists()) {
            throw new \RuntimeException('You have not saved this meal.');
        }

        $meal->saves()->detach($user->id);
    }
}
