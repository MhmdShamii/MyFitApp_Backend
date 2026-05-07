<?php

namespace App\Services\User;

use App\Enums\UserOnboardingSteps;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserService
{

    public function updateRole(User $user, string $role, User $admin): User
    {
        if ($user->id === $admin->id) {
            abort(422, 'You cannot change your own role.');
        }

        $user->role = $role;
        $user->save();

        return $user->fresh();
    }

    public function getUsers(?string $search, ?string $role, ?string $onboardingStep, int $perPage = 20): CursorPaginator
    {
        return User::query()
            ->when($search, function ($q) use ($search) {
                $lower = strtolower($search);
                $q->whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", ["%{$lower}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lower}%"]);
            })
            ->when($role, fn($q) => $q->where('role', $role))
            ->when($onboardingStep, fn($q) => $q->where('onboarding_step', $onboardingStep))
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function returnUser(Request $request): User
    {
        return $request->user()->load('profile', 'country');
    }

    public function getUserPublicProfile(User $targetUser, User $authUser): User
    {
        $targetUser->loadCount(['followers', 'following'])->load('country');

        $targetUser->is_following = $authUser->following()->where('followed_id', $targetUser->id)->exists();
        $targetUser->follows_you  = $targetUser->following()->where('followed_id', $authUser->id)->exists();

        return $targetUser;
    }

    public function findUserEmailExist(string $email): bool
    {
        return User::findByEmail($email)->first() ? true : false;
    }

    public function updateUserAvatar(User $user, UploadedFile $file): User
    {

        return $this->updateUserImage($user, $file);
    }

    public function deleteUserAvatar(User $user): User
    {
        $oldPath = $user->image;

        $user = $this->deleteUserImage($user);
        $this->deleteUserImageFile($oldPath);

        return $user;
    }

    public function updateUserCoverImage(User $user, UploadedFile $file): User
    {
        $oldPath = $user->cover_image;

        $newPath = $file->storeAs(
            'covers',
            $this->generateImageName('Cover', $file),
            's3'
        );

        try {
            DB::transaction(function () use ($user, $newPath) {
                $user->update(['cover_image' => $newPath]);
            });

            $this->deleteUserCoverImageFile($oldPath);

            return $user->fresh();
        } catch (\Throwable $e) {
            $this->deleteUserCoverImageFile($newPath);

            throw new \RuntimeException('Failed to update cover image. Please try again later.');
        }
    }

    public function deleteUserCoverImage(User $user): User
    {
        $oldPath = $user->cover_image;

        $user->update(['cover_image' => 'default_cover.png']);
        $this->deleteUserCoverImageFile($oldPath);

        return $user->fresh();
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    public function completeMainInfo(User $user): User
    {
        if ($user->onboarding_step === UserOnboardingSteps::MAIN_INFO) {
            $user->onboarding_step = UserOnboardingSteps::BASIC_INFO;
            $user->save();
        }
        return $user->fresh();
    }

    // ====== Helper Functions ======

    private function generateImageName(string $prefix, UploadedFile $file): string
    {
        return $prefix .
            "_" .
            pathinfo(str_replace(' ', '_', $file->getClientOriginalName()), PATHINFO_FILENAME) .
            "_" .
            Str::uuid() .
            "." .
            $file->extension();
    }

    private function updateUserImage(User $user, UploadedFile $file): User
    {
        $oldPath = $user->image;

        $newPath = $file->storeAs(
            'avatars',
            $this->generateImageName('Avatar', $file),
            's3'
        );

        try {
            DB::transaction(function () use ($user, $newPath) {
                $user->update(['image' => $newPath]);
            });

            $this->deleteUserImageFile($oldPath);

            return $user->fresh();
        } catch (\Throwable $e) {
            $this->deleteUserImageFile($newPath);

            throw new \RuntimeException('Failed to update user avatar. Please try again later.');
        }
    }

    private function deleteUserImage(User $user): User
    {
        $user->update([
            'image' => "default.png",
        ]);
        return $user->fresh();
    }

    private function deleteUserImageFile(?string $imagePath): void
    {
        if ($imagePath && $imagePath !== 'default.png') {
            Storage::disk('s3')->delete($imagePath);
        }
    }

    private function deleteUserCoverImageFile(?string $imagePath): void
    {
        if ($imagePath && $imagePath !== 'default_cover.png') {
            Storage::disk('s3')->delete($imagePath);
        }
    }
}
