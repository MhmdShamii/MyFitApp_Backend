<?php

namespace App\Http\Controllers\Meal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meal\SearchIngredientRequest;
use App\Http\Resources\Meal\IngredientResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ingredient;
use App\Services\Meal\IngredientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    use ApiResponse;

    public function __construct(private IngredientService $ingredientService) {}

    public function search(SearchIngredientRequest $request): JsonResponse
    {
        $ingredients = $this->ingredientService->search($request->input('query'));

        return $this->success(
            IngredientResource::collection($ingredients),
            'Ingredients fetched successfully',
            'ingredients'
        );
    }

    public function unverified(Request $request): JsonResponse
    {
        $paginator = $this->ingredientService->unverified();

        return $this->paginated(
            IngredientResource::collection($paginator->items()),
            [
                'next_cursor'  => $paginator->nextCursor()?->encode(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'Unverified ingredients fetched successfully'
        );
    }

    public function approve(Ingredient $ingredient): JsonResponse
    {
        if ($ingredient->verified) {
            return $this->error('Ingredient is already verified.', 422);
        }

        $ingredient = $this->ingredientService->approve($ingredient);

        return $this->success(new IngredientResource($ingredient), 'Ingredient approved successfully.', 'ingredient');
    }

    public function destroy(Ingredient $ingredient): JsonResponse
    {
        $this->ingredientService->delete($ingredient);

        return $this->success(null, 'Ingredient deleted successfully.');
    }
}
