<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
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
    function login()
    {
        // login logic
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
