<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthController extends Controller
{
    use ApiResponse; // methods: success(data, message, code), error (message, code, errors)
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $registerRequest): JsonResponse
    {
        $data = $registerRequest->validated();
        $result = $this->authService->register($data);

        return $this->success(
            $this->authResponseData($result['user'], $result['token']),
            'User registered successfully',
            201
        );
    }

    public function login(LoginRequest $loginRequest): JsonResponse
    {
        try {
            $result = $this->authService->login($loginRequest->validated());

            return $this->success(
                $this->authResponseData($result['user'], $result['token']),
                'User logged in successfully',
                200
            );
        } catch (UnauthorizedHttpException $e) {
            return $this->error($e->getMessage(), 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully', 200);
    }

    public function logoutFromAllDevices(Request $request): JsonResponse
    {
        $this->authService->logoutFromAllDevices($request->user());

        return $this->success(null, 'Logged out from all devices successfully', 200);
    }

    //======== Helper Functions =========//

    private function authResponseData(User $user, string $token): array
    {
        return [
            'user'  => new UserResource($user),
            'token' => $token,
        ];
    }
}
