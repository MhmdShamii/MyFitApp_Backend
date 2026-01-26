<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $registerRequest): JsonResponse
    {
        $data = $registerRequest->validated();
        $result = $this->authService->register($data);

        return $this->responseEnvelope(
            $this->returnUserData($result['user'], $result['token']),
            'User registered successfully',
            201
        );
    }

    public function login(LoginRequest $loginRequest): JsonResponse
    {
        try {
            $result = $this->authService->login($loginRequest->validated());

            return $this->responseEnvelope(
                $this->returnUserData($result['user'], $result['token']),
                'User logged in successfully',
                200
            );
        } catch (UnauthorizedHttpException $e) {
            return $this->responseEnvelope(null, $e->getMessage(), 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->responseEnvelope(null, 'Logged out successfully', 200);
    }

    public function logoutFromAllDevices(Request $request): JsonResponse
    {
        $this->authService->logoutFromAllDevices($request->user());

        return $this->responseEnvelope(null, 'Logged out from all devices successfully', 200);
    }

    //======== Helper Functions =========//

    private function responseEnvelope(?array $data, string $message, int $statusCode)
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $statusCode);
    }

    private function returnUserData(User $user, string $token): array
    {
        return [
            'user'  => $user,
            'token' => $token,
        ];
    }
}
