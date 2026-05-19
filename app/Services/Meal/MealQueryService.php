<?php

namespace App\Services\Meal;

use App\Enums\MealVisibility;
use App\Models\MealPost;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

class MealQueryService
{
    public function getUserPublicMeals(User $owner, User $viewer, int $perPage = 12): CursorPaginator
    {
        return MealPost::with([
                'mealMacro',
                'likes' => fn($q) => $q->where('user_id', $viewer->id),
                'saves' => fn($q) => $q->where('user_id', $viewer->id),
            ])
            ->whereHas('userProfile', fn($q) => $q->where('user_id', $owner->id))
            ->whereNotNull('confirmed_at')
            ->where('visibility', MealVisibility::PUBLIC)
            ->orderByDesc('confirmed_at')
            ->cursorPaginate($perPage);
    }

    public function getUserPrivateMeals(User $owner, int $perPage = 12): CursorPaginator
    {
        return MealPost::with([
                'mealMacro',
                'likes' => fn($q) => $q->where('user_id', $owner->id),
                'saves' => fn($q) => $q->where('user_id', $owner->id),
            ])
            ->whereHas('userProfile', fn($q) => $q->where('user_id', $owner->id))
            ->whereNotNull('confirmed_at')
            ->where('visibility', MealVisibility::PRIVATE)
            ->orderByDesc('confirmed_at')
            ->cursorPaginate($perPage);
    }

    public function getSavedMeals(User $user, int $perPage = 12): CursorPaginator
    {
        return MealPost::with([
                'mealMacro',
                'likes' => fn($q) => $q->where('user_id', $user->id),
                'saves' => fn($q) => $q->where('user_id', $user->id),
            ])
            ->whereHas('saves', fn($q) => $q->where('user_id', $user->id))
            ->whereNotNull('confirmed_at')
            ->orderByDesc('confirmed_at')
            ->cursorPaginate($perPage);
    }

    public function getById(int $id, User $viewer): MealPost
    {
        $meal = MealPost::with(['ingredients', 'mealMacro', 'preparationSteps', 'userProfile.user'])
            ->whereNotNull('confirmed_at')
            ->findOrFail($id);

        if ($meal->visibility === MealVisibility::PRIVATE) {
            $ownerId = $meal->userProfile?->user?->id;

            if ($viewer->id !== $ownerId) {
                abort(404);
            }
        }

        return $meal;
    }
}
