<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Resources\Meal\MealCardResource;
use App\Http\Responses\ApiResponse;
use App\Models\MealPost;
use App\Services\Meal\MealQueryService;
use App\Services\Social\SaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SaveController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SaveService $saveService,
        private MealQueryService $mealQueryService,
    ) {}

    public function save(MealPost $meal): JsonResponse
    {
        try {
            $this->saveService->save(Auth::user(), $meal);

            return $this->success(message: 'Meal saved successfully.', status: 201);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }
    }

    public function unsave(MealPost $meal): JsonResponse
    {
        try {
            $this->saveService->unsave(Auth::user(), $meal);

            return $this->success(message: 'Meal unsaved successfully.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function index(): JsonResponse
    {
        $result = $this->mealQueryService->getSavedMeals(Auth::user());

        return $this->paginated(
            MealCardResource::collection($result),
            [
                'next_cursor' => $result->nextCursor()?->encode(),
                'prev_cursor' => $result->previousCursor()?->encode(),
                'per_page'    => $result->perPage(),
            ],
            'Saved meals retrieved.'
        );
    }
}
