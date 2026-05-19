<?php

namespace App\Services\Social;

use App\Enums\MealVisibility;
use App\Models\MealPost;
use App\Models\MealPostComment;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class FeedService
{
    public function getFeed(?User $viewer, int $perPage = 12): CursorPaginator
    {
        $query = MealPost::with([
                'mealMacro',
                'ingredients',
                'preparationSteps',
                'userProfile.user',
                ...($viewer ? ['likes' => fn($q) => $q->where('user_id', $viewer->id)] : []),
                ...($viewer ? ['saves' => fn($q) => $q->where('user_id', $viewer->id)] : []),
            ])
            ->whereNotNull('confirmed_at')
            ->where('visibility', MealVisibility::PUBLIC);

        if ($viewer) {
            $query->whereHas('userProfile', fn($q) => $q->where('user_id', '!=', $viewer->id));
        }

        $posts = $query->orderByDesc('confirmed_at')->cursorPaginate($perPage);
        $items = collect($posts->items());

        $this->attachFirstComment($items);
        $this->attachFollowStatus($items, $viewer);

        return $posts;
    }

    public function getFollowingFeed(User $viewer, int $perPage = 12): CursorPaginator
    {
        $followedIds = $viewer->following()->pluck('users.id');

        $posts = MealPost::with([
                'mealMacro',
                'ingredients',
                'preparationSteps',
                'userProfile.user',
                'likes' => fn($q) => $q->where('user_id', $viewer->id),
                'saves' => fn($q) => $q->where('user_id', $viewer->id),
            ])
            ->whereNotNull('confirmed_at')
            ->where('visibility', MealVisibility::PUBLIC)
            ->whereHas('userProfile', fn($q) => $q->whereIn('user_id', $followedIds))
            ->orderByDesc('confirmed_at')
            ->cursorPaginate($perPage);

        $items = collect($posts->items());

        $this->attachFirstComment($items);

        foreach ($items as $post) {
            $post->setAttribute('viewer_follows_author', true);
        }

        return $posts;
    }

    private function attachFollowStatus(Collection $posts, ?User $viewer): void
    {
        if (!$viewer) {
            foreach ($posts as $post) {
                $post->setAttribute('viewer_follows_author', false);
            }
            return;
        }

        $authorIds = $posts
            ->map(fn($p) => $p->userProfile?->user?->id)
            ->filter()
            ->unique()
            ->values();

        $followedIds = $viewer->following()
            ->whereIn('users.id', $authorIds)
            ->pluck('users.id')
            ->flip();

        foreach ($posts as $post) {
            $authorId = $post->userProfile?->user?->id;
            $post->setAttribute('viewer_follows_author', $followedIds->has($authorId));
        }
    }

    private function attachFirstComment(Collection $posts): void
    {
        $ids = $posts->pluck('id');

        $firstComments = MealPostComment::with('user')
            ->whereIn('meal_post_id', $ids)
            ->whereNull('parent_id')
            ->oldest()
            ->get()
            ->groupBy('meal_post_id')
            ->map(fn($group) => $group->first());

        foreach ($posts as $post) {
            $comment = $firstComments->get($post->id);
            $post->setRelation('firstComment', $comment);
        }
    }
}
