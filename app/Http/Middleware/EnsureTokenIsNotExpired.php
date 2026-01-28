<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsNotExpired
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {

        $bearer = $request->bearerToken();

        if (!$bearer) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($bearer);


        if (!$token) {
            return $next($request);
        }

        if ($token->expires_at && now()->greaterThan($token->expires_at)) {
            $token->delete();
            return $this->error('Token expired. Please login again.', 401);
        }

        return $next($request);
    }
}
