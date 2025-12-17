<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

//Authentication apis
Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')->middleware('throttle:10,1');
    Route::post('/login', 'login')->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', 'me');
        Route::post('/logout', 'logout');
    });
});
