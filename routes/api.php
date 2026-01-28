<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    });

    Route::middleware(['token.not_expired', 'auth:sanctum'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutFromAllDevices']);
        });
        Route::get('/me', [UserController::class, 'me']);
    });
});
