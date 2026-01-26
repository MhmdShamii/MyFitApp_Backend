<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): array
    {
        // 1. Hash password
        $data['password'] = Hash::make($data['password']);

        // 2. Create user
        $user = User::create($data);

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
        $user = User::findByEmail($data['email'])->first();

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
