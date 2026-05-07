<?php

use App\Exceptions\AiServiceException;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureMealIsLoggable;
use App\Http\Middleware\EnsureOnboardingStep;
use App\Http\Middleware\EnsureOwnership;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->alias([
            'ensure.step'   => EnsureOnboardingStep::class,
            'ensure.owns'   => EnsureOwnership::class,
            'meal.loggable' => EnsureMealIsLoggable::class,
            'admin'         => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AiServiceException $e) {
            return response()->json([
                'data'    => null,
                'message' => $e->getMessage(),
                'errors'  => null,
            ], 503);
        });
    })->create();
