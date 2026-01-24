<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }


    function register(RegisterRequest $registerRequest)
    {
        // 1. Get validated data
        $data = $registerRequest->validated();

        $result = $this->authService->register($data);

        // 3. Return response
        return response()->json([
            'user'  => $result['user'],
            'token' => $result['token'],
        ], 201);
    }
    function login(LoginRequest $loginRequest)
    {
        // 1. Get validated data
        $data = $loginRequest->validated();

        $result = $this->authService->login($data);

        // 3. Return response
        return response()->json([
            'user'  => $result['user'],
            'token' => $result['token'],
        ], 200);
    }

    function me()
    {
        // profile logic
    }
    function logout()
    {
        // logout logic
    }
}
