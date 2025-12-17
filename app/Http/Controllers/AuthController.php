<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;

class AuthController extends Controller
{

    protected AuthService $authService;

    function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    function register(RegisterRequest $request)
    {
        $validatedData = $request->validated();

        $result = $this->authService->register($validatedData);

        return response()->json(
            [
                "user" => new UserResource($result['user']),
                "access_token" => $result['access_token']
            ],
            201
        );
    }
}
