<?php

namespace App\Services\Meal;

use App\Models\Ingredient;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

class IngredientService
{
    public function search(string $query): Collection
    {
        $lower = strtolower($query);

        return Ingredient::whereRaw('LOWER(name_en) LIKE ?', ["%{$lower}%"])
            ->orWhereRaw('LOWER(name_ar) LIKE ?', ["%{$lower}%"])
            ->orderByRaw("CASE WHEN LOWER(name_en) LIKE ? THEN 0 ELSE 1 END", ["{$lower}%"])
            ->limit(10)
            ->get();
    }

    public function unverified(int $perPage = 20): CursorPaginator
    {
        return Ingredient::where('verified', false)
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function approve(Ingredient $ingredient): Ingredient
    {
        $ingredient->update(['verified' => true]);
        return $ingredient;
    }

    public function delete(Ingredient $ingredient): void
    {
        $ingredient->delete();
    }
}
