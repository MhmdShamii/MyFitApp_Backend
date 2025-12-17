<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    function register(RegisterRequest $request)
    {
        $validated = $request->validate();

        $hashedPassword = Hash::make($validated['password']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $hashedPassword,
        ]);

        $token = $user->createToken('react-client')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
        ], 201);
    }
}
