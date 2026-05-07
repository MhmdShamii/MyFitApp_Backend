<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Models\MealPost;
use App\Models\MealPostComment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function notifyLike(User $actor, MealPost $meal): void
    {
        if ($actor->id === $meal->user_profile_id) {
            return;
        }

        $recipient = $meal->userProfile->user_id ?? null;

        if ($recipient === null || $recipient === $actor->id) {
            return;
        }

        Notification::create([
            'user_id'  => $recipient,
            'actor_id' => $actor->id,
            'type'     => NotificationType::LIKE,
            'data'     => [
                'post_id'   => $meal->id,
                'post_name' => $meal->name,
            ],
        ]);
    }

    public function notifyComment(User $actor, MealPost $meal, MealPostComment $comment): void
    {
        $recipient = $this->resolvePostOwner($meal);

        if ($recipient === null || $recipient === $actor->id) {
            return;
        }

        Notification::create([
            'user_id'  => $recipient,
            'actor_id' => $actor->id,
            'type'     => NotificationType::COMMENT,
            'data'     => [
                'post_id'      => $meal->id,
                'post_name'    => $meal->name,
                'comment_id'   => $comment->id,
                'comment_body' => $comment->body,
            ],
        ]);
    }

    public function notifyReply(User $actor, MealPost $meal, MealPostComment $parentComment, MealPostComment $reply): void
    {
        $recipient = $parentComment->user_id;

        if ($recipient === $actor->id) {
            return;
        }

        Notification::create([
            'user_id'  => $recipient,
            'actor_id' => $actor->id,
            'type'     => NotificationType::REPLY,
            'data'     => [
                'post_id'         => $meal->id,
                'post_name'       => $meal->name,
                'comment_id'      => $reply->id,
                'comment_body'    => $reply->body,
                'parent_comment_id' => $parentComment->id,
            ],
        ]);
    }

    public function notifyRelog(User $actor, MealPost $meal): void
    {
        $recipient = $this->resolvePostOwner($meal);

        if ($recipient === null || $recipient === $actor->id) {
            return;
        }

        Notification::create([
            'user_id'  => $recipient,
            'actor_id' => $actor->id,
            'type'     => NotificationType::RELOG,
            'data'     => [
                'post_id'   => $meal->id,
                'post_name' => $meal->name,
            ],
        ]);
    }

    public function notifyCoachApplication(User $applicant, int $adminId): void
    {
        Notification::create([
            'user_id'  => $adminId,
            'actor_id' => $applicant->id,
            'type'     => NotificationType::COACH_APPLICATION,
            'data'     => [
                'applicant_name' => $applicant->first_name . ' ' . $applicant->last_name,
            ],
        ]);
    }

    public function notifyApplicationApproved(User $admin, User $applicant): void
    {
        Notification::create([
            'user_id'  => $applicant->id,
            'actor_id' => $admin->id,
            'type'     => NotificationType::COACH_APPLICATION_APPROVED,
            'data'     => [],
        ]);
    }

    public function notifyApplicationRejected(User $admin, User $applicant, string $reason): void
    {
        Notification::create([
            'user_id'  => $applicant->id,
            'actor_id' => $admin->id,
            'type'     => NotificationType::COACH_APPLICATION_REJECTED,
            'data'     => ['reason' => $reason],
        ]);
    }

    public function notifyFollow(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            return;
        }

        Notification::create([
            'user_id'  => $target->id,
            'actor_id' => $actor->id,
            'type'     => NotificationType::FOLLOW,
            'data'     => [],
        ]);
    }

    public function hasNew(User $user): bool
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->exists();
    }

    public function getAndMarkRead(User $user): Collection
    {
        $notifications = Notification::with('actor')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->latest()
            ->get();

        if ($notifications->isNotEmpty()) {
            Notification::whereIn('id', $notifications->pluck('id'))
                ->update(['read_at' => now()]);
        }

        return $notifications;
    }

    private function resolvePostOwner(MealPost $meal): ?int
    {
        $meal->loadMissing('userProfile');

        return $meal->userProfile?->user_id;
    }
}
