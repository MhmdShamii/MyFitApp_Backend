<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpFoundation\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $registerRequest)
    {
        $data = $registerRequest->validated();
        $result = $this->authService->register($data);

        return response()->json([
            'user'  => $result['user'],
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $loginRequest)
    {
        try {
            $result = $this->authService->login($loginRequest->validated());

            return response()->json([
                'user'  => $result['user'],
                'token' => $result['token'],
            ], 200);
        } catch (UnauthorizedException $e) {
            return response()->json([
                'error'  => $e->getMessage(),
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function logoutFromAllDevices(Request $request)
    {
        $this->authService->logoutFromAllDevices($request->user());
        return response()->json(['message' => 'Logged out from all devices successfully'], 200);
    }
}
