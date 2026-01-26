<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\UnauthorizedException;

class AuthService
{
    public function register(array $data): array
    {
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $token = $user->createToken('web')->plainTextToken;
        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    function login(array $data): array
    {
        $user = User::findByEmail($data['email'])->first();

        if (!$this->isValidUser($user, $data['password'])) {
            throw new UnauthorizedException('Invalid credentials');
        }

        $token = $user->createToken('web')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
    function logout($user)
    {
        $user->tokens()->delete();
    }

    private function isValidUser($user, $password): bool
    {
        return $user && Hash::check($password, $user->password);
    }
}
