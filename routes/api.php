<?php

use App\Http\Controllers\AuthContoller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthContoller::class, 'login']);
        Route::post('/register', [AuthContoller::class, 'register']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/profile', [AuthContoller::class, 'profile']);
            Route::post('/logout', [AuthContoller::class, 'logout']);
        });
    });
});
