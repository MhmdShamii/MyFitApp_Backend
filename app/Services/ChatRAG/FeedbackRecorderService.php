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
        string $action = 'logged'
    ): void {
        try {
            $hour     = now()->hour;
            $timeSlot = $this->getTimeSlot($hour);

            $text = "{$mealTitle}. Calories: {$calories} kcal, Protein: {$protein}g, Carbs: {$carbs}g, Fats: {$fats}g";

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
}
