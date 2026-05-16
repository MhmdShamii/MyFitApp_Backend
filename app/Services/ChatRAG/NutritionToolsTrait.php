<?php

namespace App\Services\ChatRAG;

use App\Enums\DailyLogType;
use App\Enums\MealVisibility;
use App\Models\DailyLog;
use App\Models\DailySummary;
use App\Models\MealPost;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

trait NutritionToolsTrait
{
    private function getTodayLogs(string $profileId): array
    {
        $profile = UserProfile::findOrFail($profileId);
        $summary = DailySummary::where('user_id', $profile->user_id)
            ->whereDate('date', today())
            ->first();

        if (! $summary) {
            return ['text' => 'No meals logged today.', 'data' => null];
        }

        $logs = DailyLog::where('daily_summary_id', $summary->id)
            ->orderBy('logged_at')
            ->get(['log_name', 'calories', 'protein', 'carbs', 'fats', 'fiber', 'logged_at']);

        $caloriesRemaining = $summary->calories_target - $summary->calories_consumed;
        $proteinRemaining = $summary->protein_target - $summary->protein_consumed;
        $carbsRemaining = $summary->carbs_target - $summary->carbs_consumed;
        $fatsRemaining = $summary->fats_target - $summary->fats_consumed;

        $mealList = $logs->isNotEmpty()
            ? $logs->map(fn ($log, $i) => ($i + 1).". {$log->log_name} — {$log->calories} kcal · {$log->protein}g protein · {$log->carbs}g carbs · {$log->fats}g fats · {$log->fiber}g fiber (logged {$log->logged_at->format('H:i')})"
            )->implode("\n")
            : 'None';

        $text = <<<RESULT
        Today's Log ({$summary->logs_count} meal(s) logged):
        - Calories : {$summary->calories_consumed} / {$summary->calories_target} kcal ({$caloriesRemaining} kcal remaining)
        - Protein  : {$summary->protein_consumed}g / {$summary->protein_target}g ({$proteinRemaining}g remaining)
        - Carbs    : {$summary->carbs_consumed}g / {$summary->carbs_target}g ({$carbsRemaining}g remaining)
        - Fats     : {$summary->fats_consumed}g / {$summary->fats_target}g ({$fatsRemaining}g remaining)

        Meals logged:
        {$mealList}
        RESULT;

        return [
            'text' => $text,
            'data' => [
                'logs_count' => $summary->logs_count,
                'calories_consumed' => (float) $summary->calories_consumed,
                'calories_target' => (float) $summary->calories_target,
                'protein_consumed' => (float) $summary->protein_consumed,
                'protein_target' => (float) $summary->protein_target,
                'carbs_consumed' => (float) $summary->carbs_consumed,
                'carbs_target' => (float) $summary->carbs_target,
                'fats_consumed' => (float) $summary->fats_consumed,
                'fats_target' => (float) $summary->fats_target,
                'meals' => $logs->map(fn ($l) => [
                    'name' => $l->log_name,
                    'calories' => (float) $l->calories,
                    'logged_at' => $l->logged_at->format('H:i'),
                ])->toArray(),
            ],
        ];
    }

    private function getWeeklySummary(string $profileId, int $days = 7): array
    {
        $profile = UserProfile::findOrFail($profileId);
        $summaries = DailySummary::where('user_id', $profile->user_id)
            ->where('date', '>=', today()->subDays($days))
            ->orderBy('date')
            ->get();

        if ($summaries->isEmpty()) {
            return ['text' => "No data found for the last {$days} days.", 'data' => null];
        }

        $totalCalories = 0;
        $daysHit = 0;
        $daysMissed = 0;
        $best = null;
        $worst = null;
        $lines = [];

        foreach ($summaries as $s) {
            $hit = $s->calories_consumed >= $s->calories_target * 0.9
                && $s->calories_consumed <= $s->calories_target * 1.1;

            $status = $hit ? 'HIT' : 'MISSED';
            $hit ? $daysHit++ : $daysMissed++;
            $totalCalories += $s->calories_consumed;

            $deviation = abs($s->calories_consumed - $s->calories_target);
            if (! $best || $deviation < abs($best->calories_consumed - $best->calories_target)) {
                $best = $s;
            }
            if (! $worst || $deviation > abs($worst->calories_consumed - $worst->calories_target)) {
                $worst = $s;
            }

            $lines[] = "- {$s->date->format('Y-m-d')}: {$s->calories_consumed} / {$s->calories_target} kcal | protein {$s->protein_consumed}g / {$s->protein_target}g | [{$status}]";
        }

        $avgCalories = round($totalCalories / $summaries->count(), 1);
        $dailyLines = implode("\n", $lines);
        $bestDay = $best?->date->format('Y-m-d') ?? 'N/A';
        $worstDay = $worst?->date->format('Y-m-d') ?? 'N/A';

        $text = <<<RESULT
        Weekly Summary (last {$days} days):
        {$dailyLines}

        - Average daily calories : {$avgCalories} kcal
        - Days target hit        : {$daysHit}
        - Days target missed     : {$daysMissed}
        - Best day  (closest to target)    : {$bestDay} ({$best?->calories_consumed} / {$best?->calories_target} kcal)
        - Worst day (furthest from target) : {$worstDay} ({$worst?->calories_consumed} / {$worst?->calories_target} kcal)
        RESULT;

        return ['text' => $text, 'data' => null];
    }

    private function getUserTargets(string $profileId): array
    {
        $profile = UserProfile::findOrFail($profileId);
        $fiberTarget = $profile->daily_fiber_g ?? 25;

        $text = <<<RESULT
        User Targets:
        - Calories     : {$profile->daily_calorie_target} kcal
        - Protein      : {$profile->daily_protein_g}g
        - Carbs        : {$profile->daily_carbs_g}g
        - Fat          : {$profile->daily_fat_g}g
        - Fiber        : {$fiberTarget}g
        - Goal         : {$profile->goal?->value}
        - Activity     : {$profile->activity_level?->value}
        RESULT;

        return ['text' => $text, 'data' => null];
    }

    private function getMealDetails(int $mealPostId): array
    {
        $meal = MealPost::with(['mealMacro', 'ingredients', 'preparationSteps'])->find($mealPostId);

        if (! $meal) {
            return ['text' => 'Meal not found.', 'data' => null];
        }

        $macro = $meal->mealMacro;

        $ingredients = $meal->ingredients->isNotEmpty()
            ? $meal->ingredients->map(fn ($i) => "  - {$i->name_en}: {$i->pivot->portion} {$i->pivot->unit}")->implode("\n")
            : '  Not available';

        $steps = $meal->preparationSteps->isNotEmpty()
            ? $meal->preparationSteps->map(fn ($s) => "  {$s->step_number}. {$s->description}")->implode("\n")
            : '  Not provided';

        $text = <<<RESULT
        Meal: {$meal->name}
        Description: {$meal->description}

        Macros per serving:
        - Calories : {$macro?->calories} kcal
        - Protein  : {$macro?->protein}g
        - Carbs    : {$macro?->carbs}g
        - Fats     : {$macro?->fats}g
        - Fiber    : {$macro?->fiber}g

        Ingredients:
        {$ingredients}

        Preparation:
        {$steps}
        RESULT;

        return ['text' => $text, 'data' => null];
    }

    private function searchMeals(string $query, ?int $maxCalories = null, ?int $minProtein = null): array
    {
        $results = MealPost::query()
            ->join('meal_macros', 'meal_posts.fingerprint', '=', 'meal_macros.fingerprint')
            ->where('meal_posts.visibility', MealVisibility::PUBLIC->value)
            ->whereNotNull('meal_posts.confirmed_at')
            ->whereNull('meal_posts.deleted_at')
            ->when($query, fn ($q) => $q->where('meal_posts.name', 'LIKE', "%{$query}%"))
            ->when($maxCalories, fn ($q) => $q->where('meal_macros.calories', '<=', $maxCalories))
            ->when($minProtein, fn ($q) => $q->where('meal_macros.protein', '>=', $minProtein))
            ->orderBy('meal_posts.relogs_count', 'desc')
            ->limit(5)
            ->select('meal_posts.id', 'meal_posts.name', 'meal_macros.calories', 'meal_macros.protein', 'meal_macros.carbs', 'meal_macros.fats')
            ->get();

        if ($results->isEmpty()) {
            return ['text' => 'No meals found matching your criteria.', 'data' => []];
        }

        $lines = $results->map(fn ($m) => "- [ID:{$m->id}] {$m->name} | {$m->calories} kcal | protein {$m->protein}g | carbs {$m->carbs}g | fats {$m->fats}g"
        )->implode("\n");

        $data = $results->map(fn ($m) => [
            'meal_post_id' => $m->id,
            'name' => $m->name,
            'calories' => (float) $m->calories,
            'protein' => (float) $m->protein,
            'carbs' => (float) $m->carbs,
            'fats' => (float) $m->fats,
        ])->toArray();

        return ['text' => "Meals matching '{$query}':\n{$lines}", 'data' => $data];
    }

    private function logMeal(string $profileId, string $name, float $calories, float $protein, float $carbs, float $fats, float $fiber = 0): array
    {
        return DB::transaction(function () use ($profileId, $name, $calories, $protein, $carbs, $fats, $fiber) {
            $profile = UserProfile::findOrFail($profileId);

            $summary = DailySummary::firstOrCreate(
                ['user_id' => $profile->user_id, 'date' => today()],
                [
                    'calories_target' => $profile->daily_calorie_target,
                    'protein_target' => $profile->daily_protein_g,
                    'carbs_target' => $profile->daily_carbs_g,
                    'fats_target' => $profile->daily_fat_g,
                    'fiber_target' => $profile->daily_fiber_g ?? 25,
                    'logs_count' => 0,
                ]
            );

            DailyLog::create([
                'user_id' => $profile->user_id,
                'daily_summary_id' => $summary->id,
                'type' => DailyLogType::ESTIMATE,
                'log_name' => $name,
                'calories' => $calories,
                'protein' => $protein,
                'carbs' => $carbs,
                'fats' => $fats,
                'fiber' => $fiber,
                'logged_at' => now(),
                'confirmed_at' => now(),
            ]);

            $summary->increment('calories_consumed', $calories);
            $summary->increment('protein_consumed', $protein);
            $summary->increment('carbs_consumed', $carbs);
            $summary->increment('fats_consumed', $fats);
            $summary->increment('fiber_consumed', $fiber);
            $summary->increment('logs_count');
            $summary->refresh();

            $caloriesRemaining = (float) ($summary->calories_target - $summary->calories_consumed);
            $proteinRemaining = (float) ($summary->protein_target - $summary->protein_consumed);

            $text = <<<RESULT
            Logged: {$name}
            - Calories : {$calories} kcal | Protein: {$protein}g | Carbs: {$carbs}g | Fats: {$fats}g | Fiber: {$fiber}g
            - Calories remaining : {$caloriesRemaining} kcal
            - Protein remaining  : {$proteinRemaining}g
            RESULT;

            return [
                'text' => $text,
                'data' => [
                    'meal_name' => $name,
                    'calories' => $calories,
                    'protein' => $protein,
                    'carbs' => $carbs,
                    'fats' => $fats,
                    'fiber' => $fiber,
                    'calories_remaining' => $caloriesRemaining,
                    'protein_remaining' => $proteinRemaining,
                ],
            ];
        });
    }

    private function updateDailyTargets(string $profileId, ?int $calories, ?int $protein, ?int $carbs, ?int $fat): array
    {
        return DB::transaction(function () use ($profileId, $calories, $protein, $carbs, $fat) {
            $profile = UserProfile::findOrFail($profileId);

            $updates = array_filter([
                'daily_calorie_target' => $calories,
                'daily_protein_g'      => $protein,
                'daily_carbs_g'        => $carbs,
                'daily_fat_g'          => $fat,
            ], fn ($v) => $v !== null);

            if (empty($updates)) {
                return ['text' => 'No targets provided to update.', 'data' => null];
            }

            $profile->update($updates);
            $profile->refresh();

            $summary = DailySummary::where('user_id', $profile->user_id)
                ->whereDate('date', today())
                ->first();

            if ($summary) {
                $summaryUpdates = array_filter([
                    'calories_target' => $calories,
                    'protein_target'  => $protein,
                    'carbs_target'    => $carbs,
                    'fats_target'     => $fat,
                ], fn ($v) => $v !== null);

                $summary->update($summaryUpdates);
            }

            $text = <<<RESULT
            Daily targets updated:
            - Calories : {$profile->daily_calorie_target} kcal
            - Protein  : {$profile->daily_protein_g}g
            - Carbs    : {$profile->daily_carbs_g}g
            - Fat      : {$profile->daily_fat_g}g
            RESULT;

            return [
                'text' => $text,
                'data' => [
                    'calories' => (int) $profile->daily_calorie_target,
                    'protein'  => (int) $profile->daily_protein_g,
                    'carbs'    => (int) $profile->daily_carbs_g,
                    'fat'      => (int) $profile->daily_fat_g,
                ],
            ];
        });
    }

    private function deleteLastLog(string $profileId): array
    {
        return DB::transaction(function () use ($profileId) {
            $profile = UserProfile::findOrFail($profileId);

            $summary = DailySummary::where('user_id', $profile->user_id)
                ->whereDate('date', today())
                ->first();

            if (! $summary) {
                return ['text' => 'No recent log found to delete.', 'data' => null];
            }

            $log = DailyLog::where('daily_summary_id', $summary->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $log) {
                return ['text' => 'No recent log found to delete.', 'data' => null];
            }

            $summary->decrement('calories_consumed', $log->calories);
            $summary->decrement('protein_consumed', $log->protein);
            $summary->decrement('carbs_consumed', $log->carbs);
            $summary->decrement('fats_consumed', $log->fats);
            $summary->decrement('fiber_consumed', $log->fiber);
            $summary->decrement('logs_count');
            $summary->refresh();

            $logName = $log->log_name;
            $logCalories = (float) $log->calories;
            $logProtein = (float) $log->protein;
            $logCarbs = (float) $log->carbs;
            $logFats = (float) $log->fats;
            $caloriesRemaining = (float) ($summary->calories_target - $summary->calories_consumed);

            $log->delete();

            $text = <<<RESULT
            Deleted: {$logName}
            - Removed  : {$logCalories} kcal | protein {$logProtein}g | carbs {$logCarbs}g | fats {$logFats}g
            - Calories remaining : {$caloriesRemaining} kcal
            RESULT;

            return [
                'text' => $text,
                'data' => [
                    'meal_name' => $logName,
                    'calories_removed' => $logCalories,
                    'calories_remaining' => $caloriesRemaining,
                ],
            ];
        });
    }
}
