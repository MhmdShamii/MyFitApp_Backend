<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

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
