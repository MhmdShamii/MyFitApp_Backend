<?php

use App\Http\Controllers\Analytics\AdminAnalyticsController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Meal\DailyLogingController;
use App\Http\Controllers\Meal\IngredientController;
use App\Http\Controllers\Meal\MealController;
use App\Http\Controllers\User\HealthConditionController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\UserProfileController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Social\CommentController;
use App\Http\Controllers\Social\FeedController;
use App\Http\Controllers\Social\FollowController;
use App\Http\Controllers\Social\LikeController;
use App\Http\Controllers\Coach\CoachApplicationController;
use App\Http\Controllers\User\UserMealController;
use App\Http\Controllers\chatAiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/feed', [FeedController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('/check-email', [UserController::class, 'checkEmail']);
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed'])->name('verification.verify');
        Route::post('/email/resend', [AuthController::class, 'resendVerification']);
        Route::post('/google', [AuthController::class, 'googleLogin']);
    });

    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('chat')->group(function () {
            Route::post('/', [chatAiController::class, 'sendMessage']);
            Route::get('/history', [chatAiController::class, 'getMessageHistory']);
        });

        Route::get('/health-conditions', [HealthConditionController::class, 'index']);

        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutFromAllDevices']);
        });

        Route::prefix('users/me')->group(function () {
            Route::get('/', [UserController::class, 'show']);
            Route::patch('/', [UserController::class, 'update']);
            Route::post('/complete-main-info', [UserController::class, 'storeMainInfo'])->middleware('ensure.step:main_info');
            Route::post('/complete-basic-info', [UserProfileController::class, 'storeBasicInfo'])->middleware('ensure.step:basic_info');
            Route::post('/complete-targets', [UserProfileController::class, 'storeTargets'])->middleware('ensure.step:targets');
            Route::patch('/targets', [UserProfileController::class, 'updateTargets']);
            Route::get('/health-conditions', [HealthConditionController::class, 'userConditions'])->middleware('ensure.step:health_conditions');
            Route::post('/health-conditions', [HealthConditionController::class, 'store'])->middleware('ensure.step:health_conditions');
            Route::delete('/health-conditions/{id}', [HealthConditionController::class, 'destroy'])->middleware('ensure.step:health_conditions');
            Route::post('/complete-health-conditions', [HealthConditionController::class, 'complete'])->middleware('ensure.step:health_conditions');
            
            Route::prefix('settings')->group(function () {
                Route::get('/health-conditions', [HealthConditionController::class, 'userConditions']);
                Route::delete('/health-conditions/{id}', [HealthConditionController::class, 'destroy']);
            });

            Route::post('/avatar', [UserController::class, 'storeAvatar']);
            Route::delete('/avatar', [UserController::class, 'destroyAvatar']);
            Route::post('/cover-image', [UserController::class, 'storeCoverImage']);
            Route::delete('/cover-image', [UserController::class, 'destroyCoverImage']);
            Route::post('/log', [DailyLogingController::class, 'logCustomMeal']);
            Route::post('/log/estimate', [DailyLogingController::class, 'logEstimatedMeal']);
            Route::post('/log/{log}/confirm', [DailyLogingController::class, 'confirmLog'])->middleware('ensure.owns:log');
            Route::post('/log/{meal}', [DailyLogingController::class, 'logMeal'])->middleware('meal.loggable');
            Route::delete('/log/{log}', [DailyLogingController::class, 'removeDailyLog'])->middleware('ensure.owns:log');

            Route::prefix('/analytics')->group(function () {
                Route::get('/streak', [AnalyticsController::class, 'streak']);
                Route::get('/today', [AnalyticsController::class, 'todayLogs']);
                Route::get('/today/macros', [AnalyticsController::class, 'todayMacros']);
                Route::get('/day', [AnalyticsController::class, 'dayLogs']);
                Route::post('/weight', [AnalyticsController::class, 'logWeight']);
                Route::get('/weight', [AnalyticsController::class, 'weightHistory']);
                Route::get('/calories', [AnalyticsController::class, 'caloriesWeek']);
                Route::get('/macros', [AnalyticsController::class, 'macrosWeek']);
            });
        });

        Route::get('/feed/following', [FeedController::class, 'following']);

        Route::prefix('notifications')->group(function () {
            Route::get('/check', [NotificationController::class, 'check']);
            Route::get('/', [NotificationController::class, 'index']);
        });

        Route::prefix('meals')->group(function () {
            Route::get('/{meal}', [MealController::class, 'show']);
            Route::post('/', [MealController::class, 'store']);
            Route::post('/{meal}/confirm', [MealController::class, 'confirm'])->middleware('ensure.owns:meal,user_profile_id');
            Route::post('/{meal}/discard', [MealController::class, 'discard'])->middleware('ensure.owns:meal,user_profile_id');
            Route::post('/{meal}/like', [LikeController::class, 'like']);
            Route::delete('/{meal}/like', [LikeController::class, 'unlike']);
            Route::get('/{meal}/comments', [CommentController::class, 'index']);
            Route::post('/{meal}/comments', [CommentController::class, 'store']);
            Route::get('/{meal}/comments/{comment}/replies', [CommentController::class, 'replies']);
            Route::post('/{meal}/comments/{comment}/replies', [CommentController::class, 'reply']);
            Route::delete('/{meal}/comments/{comment}', [CommentController::class, 'destroy']);
        });

        Route::prefix('users')->group(function () {
            Route::get('/{user}', [UserController::class, 'userProfile']);
            Route::post('/{user}/follow', [FollowController::class, 'follow']);
            Route::delete('/{user}/follow', [FollowController::class, 'unfollow']);
            Route::get('/{user}/followers', [FollowController::class, 'followers']);
            Route::get('/{user}/following', [FollowController::class, 'following']);
            Route::get('/{user}/meals', [UserMealController::class, 'publicMeals']);
            Route::get('/{user}/meals/private', [UserMealController::class, 'privateMeals']);
        });

        Route::prefix('ingredients')->group(function () {
            Route::post('/search', [IngredientController::class, 'search']);
        });

        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::get('/analytics', [AdminAnalyticsController::class, 'overview']);

            Route::get('/users', [UserController::class, 'index']);
            Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);

            Route::get('/ingredients', [IngredientController::class, 'unverified']);
            Route::post('/ingredients/{ingredient}/approve', [IngredientController::class, 'approve']);
            Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy']);

            Route::get('/coach-applications', [CoachApplicationController::class, 'index']);
            Route::post('/coach-applications/{coachApplication}/approve', [CoachApplicationController::class, 'approve']);
            Route::post('/coach-applications/{coachApplication}/reject', [CoachApplicationController::class, 'reject']);
        });

        Route::prefix('coach-application')->group(function () {
            Route::get('/', [CoachApplicationController::class, 'show']);
            Route::post('/', [CoachApplicationController::class, 'store']);
        });
    });
});
