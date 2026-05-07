<?php

namespace App\Services\Coach;

use App\Enums\CoachApplicationStatus;
use App\Enums\UserRole;
use App\Models\CoachApplication;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CoachApplicationService
{
    public function __construct(private NotificationService $notificationService) {}

    public function getApplication(User $user): ?CoachApplication
    {
        return $user->coachApplication()->with('documents')->latest()->first();
    }


    public function submit(User $user, string $description, array $files): CoachApplication
    {
        if ($user->role === UserRole::COACH) {
            abort(422, 'You are already a coach.');
        }

        $existing = $user->coachApplication()->whereIn('status', [
            CoachApplicationStatus::PENDING->value,
            CoachApplicationStatus::APPROVED->value,
        ])->first();

        if ($existing) {
            abort(422, 'You already have an active application.');
        }

        $lastRejected = $user->coachApplication()
            ->where('status', CoachApplicationStatus::REJECTED->value)
            ->latest('reviewed_at')
            ->first();

        if ($lastRejected) {
            $canReapplyAt = $lastRejected->reviewed_at->addDays(30);
            if (now()->lt($canReapplyAt)) {
                abort(422, 'You can reapply on ' . $canReapplyAt->toDateString() . '.');
            }
        }

        $application = DB::transaction(function () use ($user, $description, $files) {
            $application = $user->coachApplication()->create([
                'description' => $description,
                'status'      => CoachApplicationStatus::PENDING,
            ]);

            foreach ($files as $file) {
                $type = $file->getMimeType() === 'application/pdf' ? 'certificate' : 'image';
                $path = $file->storeAs(
                    'coach-applications/' . $application->id,
                    $this->buildFileName($file),
                    's3'
                );

                $application->documents()->create([
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'type'          => $type,
                ]);
            }

            return $application->load('documents');
        });

        User::where('role', UserRole::ADMIN)->pluck('id')->each(
            fn($adminId) => $this->notificationService->notifyCoachApplication($user, $adminId)
        );

        return $application;
    }

    public function getAll(?string $status = null, int $perPage = 20): CursorPaginator
    {
        return CoachApplication::with(['user', 'documents'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function approve(CoachApplication $application, User $admin): CoachApplication
    {
        if ($application->status !== CoachApplicationStatus::PENDING) {
            abort(422, 'Only pending applications can be approved.');
        }

        DB::transaction(function () use ($application, $admin) {
            $application->update([
                'status'      => CoachApplicationStatus::APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $user = $application->user;
            $user->role = UserRole::COACH;
            $user->save();
        });

        $this->notificationService->notifyApplicationApproved($admin, $application->user);

        return $application->load(['user', 'documents']);
    }

    public function reject(CoachApplication $application, User $admin, string $reason): CoachApplication
    {
        if ($application->status !== CoachApplicationStatus::PENDING) {
            abort(422, 'Only pending applications can be rejected.');
        }

        $application->update([
            'status'           => CoachApplicationStatus::REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
        ]);

        $this->notificationService->notifyApplicationRejected($admin, $application->user, $reason);

        return $application->load(['user', 'documents']);
    }

    private function buildFileName(UploadedFile $file): string
    {
        return uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
    }
}
