<?php

namespace App\Services\ChatRAG;

use App\Models\RecommendationFeedback;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Log;

class FeedbackRecorderService
{
    public function __construct(private EmbeddingService $embeddingService) {}

    public function record(
        string $profileId,
        string $mealTitle,
        ?int $mealPostId,
        string $sourceType,
        float $calories,
        float $protein,
        float $carbs,
        float $fats,
        string $action = 'logged',
        ?string $description = null,
        ?string $ingredientsList = null,
    ): void {
        try {
            $hour     = now()->hour;
            $timeSlot = $this->getTimeSlot($hour);

            $text         = $this->buildEmbeddingText($mealTitle, $calories, $protein, $carbs, $fats, $timeSlot, $description, $ingredientsList);
            $vector       = $this->embeddingService->generate($text);
            $embeddingStr = '[' . implode(',', $vector) . ']';

            RecommendationFeedback::create([
                'profile_id'     => $profileId,
                'meal_title'     => $mealTitle,
                'meal_post_id'   => $mealPostId,
                'source_type'    => $sourceType,
                'action'         => $action,
                'meal_time_slot' => $timeSlot,
                'logged_hour'    => $hour,
                'calories'       => $calories,
                'protein'        => $protein,
                'carbs'          => $carbs,
                'fats'           => $fats,
                'embedding'      => $embeddingStr,
            ]);
        } catch (\Throwable $e) {
            Log::error('FeedbackRecorder failed: ' . $e->getMessage(), [
                'profile_id' => $profileId,
                'meal_title' => $mealTitle,
            ]);
        }
    }

    public function getTimeSlot(int $hour): string
    {
        return match (true) {
            $hour >= 0  && $hour <= 10 => 'breakfast',
            $hour >= 11 && $hour <= 14 => 'lunch',
            $hour >= 15 && $hour <= 17 => 'snack',
            $hour >= 18 && $hour <= 22 => 'dinner',
            default                    => 'late_night',
        };
    }

    private function buildEmbeddingText(
        string $mealTitle,
        float $calories,
        float $protein,
        float $carbs,
        float $fats,
        string $timeSlot,
        ?string $description = null,
        ?string $ingredientsList = null,
    ): string {
        $text = $mealTitle . '.';

        if ($description) {
            $text .= ' ' . $description . '.';
        }

        if ($ingredientsList) {
            $text .= ' Ingredients: ' . $ingredientsList . '.';
        }

        $text .= " Calories: {$calories} kcal,";
        $text .= " Protein: {$protein}g,";
        $text .= " Carbs: {$carbs}g,";
        $text .= " Fats: {$fats}g.";
        $text .= " Meal time: {$timeSlot}.";

        return $text;
    }
}
