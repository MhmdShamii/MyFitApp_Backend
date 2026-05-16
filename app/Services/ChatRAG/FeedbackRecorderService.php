<?php

namespace App\Services\ChatRAG;

use App\Models\RecommendationFeedback;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
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

    public function getFeedbackContext(string $profileId, int $currentHour): string
    {
        $timeSlot = $this->getTimeSlot($currentHour);

        $enjoyed = DB::table('recommendation_feedback')
            ->select('meal_title', DB::raw('count(*) as count'))
            ->where('profile_id', $profileId)
            ->where('action', 'logged')
            ->where('meal_time_slot', $timeSlot)
            ->groupBy('meal_title')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $dismissed = DB::table('recommendation_feedback')
            ->select('meal_title')
            ->where('profile_id', $profileId)
            ->where('action', 'dismissed')
            ->where('meal_time_slot', $timeSlot)
            ->distinct()
            ->pluck('meal_title');

        $notChosen = DB::table('recommendation_feedback')
            ->select('meal_title')
            ->where('profile_id', $profileId)
            ->where('action', 'not_chosen')
            ->where('meal_time_slot', $timeSlot)
            ->where('shown_count', '>=', 3)
            ->pluck('meal_title');

        $blacklist = $dismissed->merge($notChosen)->unique()->values();

        if ($enjoyed->isEmpty() && $blacklist->isEmpty()) {
            return 'No recommendation history yet.';
        }

        $lines = ["Current time slot: {$timeSlot}", ''];

        if ($enjoyed->isNotEmpty()) {
            $lines[] = "Meals this user enjoys at {$timeSlot}:";
            foreach ($enjoyed as $row) {
                $times = $row->count === 1 ? '1 time' : "{$row->count} times";
                $lines[] = "- {$row->meal_title} (chosen {$times})";
            }
        }

        if ($blacklist->isNotEmpty()) {
            if ($enjoyed->isNotEmpty()) {
                $lines[] = '';
            }
            $lines[] = "Never suggest these at {$timeSlot}:";
            foreach ($blacklist as $title) {
                $lines[] = "- {$title}";
            }
        }

        return implode("\n", $lines);
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
