<?php

namespace App\Services\Auth;

use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): array
    {
        // 1. Hash password
        $data['password'] = Hash::make($data['password']);

        // 2. Create user
        $user = $this->userRepository->create($data);

        // 3. Create Sanctum token
        $token = $user->createToken('web')->plainTextToken;

        // 4. Return standardized response
        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    function login(array $data): array
    {
        //get user by email
        $user = $this->userRepository->findByEmail($data['email']);

        //check if user exists and password is correct by matchng hashed password
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return [
                'error' => 'Invalid credentials',
            ];
        }

        //create token
        $token = $user->createToken('web')->plainTextToken;

        //return response
        return [
            'user' => $user,
            'token' => $token,
        ];
    }
    //logout
    function logout($user)
    {
        $user->tokens()->delete();
    }
}
