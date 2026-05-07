<?php

namespace App\Services\Auth;

use App\Enums\UserProvider;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Analytics\PlatformStatsService;
use Google_Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    public function __construct(private PlatformStatsService $platformStatsService) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $user = $this->createUser([
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'provider' => UserProvider::LOCAL,
            ]);

            SendVerificationEmailJob::dispatch($user);

            return [
                'user' => $user,
                'token' => null,
            ];
        });
    }

    public function verifyEmail(int $id, string $hash)
    {

        $user = User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return [
                'message' => 'Email already verified',
                "status" => 404,
            ];
        }

        if (! hash_equals((string) $hash, sha1($user->email))) {
            throw new \Exception('Invalid verification link');
        }

        $user->email_verified_at = now();
        $user->save();

        $token = $this->issueToken($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function resendVerificationEmail(string $email): array
    {
        $user = User::findByEmail($email)->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        if ($user->hasVerifiedEmail()) {
            return [
                "status" => 404,
                "message" => "Email already verified",
            ];
        }

        $user->sendEmailVerificationNotification();

        return [
            "status" => 200,
            "message" => "Verification email sent",
        ];
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

    public function googleLogin(string $idToken)
    {
        return DB::transaction(function () use ($idToken) {

            $payload = $this->verifyGoogleToken($idToken);

            [
                'sub' => $googleId,
                'email' => $email,
            ] = $payload;

            $user = User::where("provider_id", $googleId)->first();

            if (! $user) {
                $user = User::findByEmail($email)->first();

                if ($user) {
                    $user = $this->linkUserToGoogleAccount($user, $payload);
                } else {
                    $user = $this->createGoogleUser($payload);
                }
            }

            $token = $this->issueToken($user, 10, "google");
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

    private function verifyGoogleToken(string $idToken): array
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));

        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            throw new UnauthorizedHttpException('', 'Invalid Google token');
        }

        if (!isset($payload['sub'], $payload['email'])) {
            throw new UnauthorizedHttpException('', 'Invalid Google payload');
        }

        if (!in_array($payload['iss'], [
            'accounts.google.com',
            'https://accounts.google.com'
        ])) {
            throw new UnauthorizedHttpException('', 'Invalid issuer');
        }

        if ($payload['aud'] !== config('services.google.client_id')) {
            throw new UnauthorizedHttpException('', 'Invalid audience');
        }

        if (!data_get($payload, 'email_verified', false)) {
            throw new UnauthorizedHttpException('', 'Email not verified');
        }

        return $payload;
    }

    private function linkUserToGoogleAccount(User $user, array $payload): User
    {
        [
            'sub' => $googleId,
            'given_name' => $firstName,
            'family_name' => $lastName,
            'picture' => $picture,
        ] = $payload;

        $user->provider = UserProvider::GOOGLE;
        $user->provider_id = $googleId;

        if (!$user->hasVerifiedEmail()) {
            $user->password = null;
            $user->email_verified_at = now();
        }
        if ($user->image === null || $user->image === 'default.png') {
            $user->image = $picture;
        }
        if (is_null($user->first_name)) {
            $user->first_name = $firstName;
        }
        if (is_null($user->last_name)) {
            $user->last_name = $lastName;
        }
        $user->save();

        return $user;
    }

    private function createGoogleUser(array $payload): User
    {
        return $this->createUser([
            'email'             => $payload['email'],
            'first_name'        => $payload['given_name'],
            'last_name'         => $payload['family_name'],
            'image'             => $payload['picture'],
            'provider'          => UserProvider::GOOGLE,
            'provider_id'       => $payload['sub'],
            'email_verified_at' => now(),
        ]);
    }

    private function createUser(array $attributes): User
    {
        $user = User::forceCreate($attributes);
        UserProfile::create(['user_id' => $user->id]);
        $this->platformStatsService->incrementNewUsers();
        return $user;
    }

    private function authenticateUser(array $data): User
    {
        $user = User::findByEmail($data['email'])->first();

        if (!$user) {
            throw new UnauthorizedHttpException('', 'invalid credentials');
        }
        if ($user->provider !== UserProvider::LOCAL && ! $user->password) {
            throw new UnauthorizedHttpException('', 'Please login with ' . $user->provider->value);
        }
        if (!$this->isValidUser($user, $data['password'])) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }
        if (! $user->hasVerifiedEmail()) {
            throw new UnauthorizedHttpException('', 'Email not verified');
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
