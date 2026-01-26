<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;


class AuthService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            return $this->createUser($data);
        });
    }

    public function login(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $user = $this->authenticateUser($data);
            $token = $this->issueToken($user, 10);

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutFromAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    //======== Helper Functions =========//

    private function createUser(array $data): array
    {
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $token = $this->issueToken($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    private function authenticateUser(array $data): User
    {
        $user = User::findByEmail($data['email'])->first();
        if (!$this->isValidUser($user, $data['password'])) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        return $user;
    }

    private function isValidUser(?User $user, string $password): bool
    {
        return $user && Hash::check($password, $user->password);
    }

    private function issueToken(User $user, int $expiresInDays = 30, string $name = 'web'): string
    {
        $newToken = $user->createToken($name);

        $newToken->accessToken->expires_at = now()->addDays($expiresInDays);
        $newToken->accessToken->save();

        return $newToken->plainTextToken;
    }
}
