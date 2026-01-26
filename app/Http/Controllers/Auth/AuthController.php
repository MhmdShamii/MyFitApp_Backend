<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpFoundation\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    function register(RegisterRequest $registerRequest)
    {
        $data = $registerRequest->validated();
        $result = $this->authService->register($data);

        return response()->json([
            'user'  => $result['user'],
            'token' => $result['token'],
        ], 201);
    }

    function login(LoginRequest $loginRequest)
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

    function me(Request $request)
    {

        return response()->json($request->user(), 200);
    }

    function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
