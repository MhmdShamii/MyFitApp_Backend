<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CompleteMainInfoRequest;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UpdateCoverImageRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\User\UserPublicProfileResource;
use App\Http\Resources\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->updateRole($user, $request->validated('role'), $request->user());

        return $this->success(new UserResource($user), 'User role updated successfully.', 'user');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->userService->getUsers(
            $request->query('search'),
            $request->query('role'),
            $request->query('onboarding_step'),
        );

        return $this->paginated(
            UserResource::collection($paginator->items()),
            [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more'    => $paginator->hasMorePages(),
            ],
            'Users fetched successfully.'
        );
    }

    public function userProfile(User $user, Request $request): JsonResponse
    {
        $profile = $this->userService->getUserPublicProfile($user, $request->user());

        return $this->success(
            new UserPublicProfileResource($profile),
            'User profile retrieved successfully',
            dataKey: 'user'
        );
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $result = $this->userService->findUserEmailExist($request->input('email'));

        return $this->success(
            $result,
            $result ? 'Email already exists' : 'Email is available',
            dataKey: 'existing_user'
        );
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($this->userService->returnUser($request)),
            'User retrieved successfully',
            dataKey: 'user'
        );
    }

    public function update(UpdateUserRequest $request): JsonResponse
    {
        return $this->success(
            new UserResource($this->userService->updateUser($request->user(), $request->validated())),
            'User updated successfully',
            dataKey: 'user'
        );
    }

    public function storeMainInfo(CompleteMainInfoRequest $request): JsonResponse
    {
        $user = $this->userService->updateUser($request->user(), $request->validated());
        $user = $this->userService->completeMainInfo($user);

        return $this->success(
            new UserResource($user),
            'Main info completed successfully',
            dataKey: 'user'
        );
    }

    public function storeAvatar(UpdateAvatarRequest $request): JsonResponse
    {

        return $this->success(
            new UserResource($this->userService->updateUserAvatar($request->user(), $request->file('avatar'))),
            'User Avatar Updated Successfuly',
            dataKey: 'user'
        );
    }

    public function destroyAvatar(Request $request): JsonResponse
    {

        return $this->success(
            new UserResource($this->userService->deleteUserAvatar($request->user())),
            'User Avatar deleted Successfuly',
            dataKey: 'user'
        );
    }

    public function storeCoverImage(UpdateCoverImageRequest $request): JsonResponse
    {
        return $this->success(
            new UserResource($this->userService->updateUserCoverImage($request->user(), $request->file('cover_image'))),
            'Cover image updated successfully',
            dataKey: 'user'
        );
    }

    public function destroyCoverImage(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($this->userService->deleteUserCoverImage($request->user())),
            'Cover image deleted successfully',
            dataKey: 'user'
        );
    }
}
