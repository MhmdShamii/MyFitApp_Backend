<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Analytics\AdminAnalyticsService;
use Illuminate\Http\JsonResponse;

class AdminAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AdminAnalyticsService $adminAnalyticsService) {}

    public function overview(): JsonResponse
    {
        return $this->success(
            $this->adminAnalyticsService->overview(),
            'Platform analytics fetched successfully.',
            'analytics'
        );
    }
}
