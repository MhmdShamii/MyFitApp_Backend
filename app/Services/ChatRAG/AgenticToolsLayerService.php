<?php

namespace App\Services\ChatRAG;

use App\Enums\DailyLogType;
use App\Enums\MealVisibility;
use App\Models\DailyLog;
use App\Models\DailySummary;
use App\Models\MealPost;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class AgenticToolsLayerService
{
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_today_logs',
                    'description' => 'Returns the user\'s food log for today including each meal\'s name, calories, macros, and time logged, plus running totals and remaining targets for calories, protein, carbs, fats, and fiber.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weekly_summary',
                    'description' => 'Returns a day-by-day calorie and protein summary for the last N days, including whether each day\'s target was hit or missed, average daily calories, and the best and worst days relative to target.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'days' => [
                                'type' => 'integer',
                                'description' => 'Number of past days to include. Defaults to 7.',
                                'default' => 7,
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_user_targets',
                    'description' => 'Returns the user\'s daily nutrition targets (calories, protein, carbs, fat, fiber), their fitness goal, and activity level.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_meal_details',
                    'description' => 'Returns full details for a specific meal post: macros per serving, ingredient list with portions and units, and preparation steps. Use the meal_post_id returned by search_meals.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'meal_post_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the meal post to retrieve details for.',
                            ],
                        ],
                        'required' => ['meal_post_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_meals',
                    'description' => 'Searches the public meal database by name and optional macro filters. Returns up to 5 results with meal name, calories, protein, carbs, fats, and meal_post_id. Use get_meal_details for full ingredient and preparation info.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Meal name keyword to search for.',
                            ],
                            'max_calories' => [
                                'type' => 'integer',
                                'description' => 'Only return meals with calories at or below this value.',
                            ],
                            'min_protein' => [
                                'type' => 'integer',
                                'description' => 'Only return meals with protein at or above this value in grams.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'log_meal',
                    'description' => 'Logs a meal to the user\'s daily food diary and updates today\'s nutrition totals. Use this when the user confirms they ate something or asks you to log a meal for them. Returns updated remaining calories and protein.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Name of the meal or food item.',
                            ],
                            'calories' => [
                                'type' => 'number',
                                'description' => 'Total calories for this meal.',
                            ],
                            'protein' => [
                                'type' => 'number',
                                'description' => 'Protein in grams.',
                            ],
                            'carbs' => [
                                'type' => 'number',
                                'description' => 'Carbohydrates in grams.',
                            ],
                            'fats' => [
                                'type' => 'number',
                                'description' => 'Fats in grams.',
                            ],
                            'fiber' => [
                                'type' => 'number',
                                'description' => 'Fiber in grams. Defaults to 0 if unknown.',
                                'default' => 0,
                            ],
                        ],
                        'required' => ['name', 'calories', 'protein', 'carbs', 'fats'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_last_log',
                    'description' => 'Removes the most recently logged meal from today\'s diary and reverses its contribution to today\'s nutrition totals. Use only when the user explicitly asks to undo or delete their last log entry.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    public function run(array $messages, string $profileId): string
    {
        $payload = [
            'model' => env('OPENAI_MODEL_CHAT', 'gpt-4o-2024-08-06'),
            'max_completion_tokens' => 1000,
            'tools' => $this->getToolDefinitions(),
            'tool_choice' => 'auto',
            'messages' => $messages,
        ];

        $lastContent = '';

        for ($i = 0; $i < 5; $i++) {
            $response = OpenAI::chat()->create($payload);
            $choice = $response->choices[0];
            $lastContent = $choice->message->content ?? $lastContent;

            if ($choice->finishReason !== 'tool_calls') {
                return $choice->message->content ?? 'I was unable to complete your request. Please try again.';
            }

            $assistantMessage = [
                'role' => 'assistant',
                'content' => $choice->message->content,
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->function->name,
                        'arguments' => $tc->function->arguments,
                    ],
                ], $choice->message->toolCalls),
            ];

            $payload['messages'][] = $assistantMessage;

            foreach ($choice->message->toolCalls as $toolCall) {
                $arguments = json_decode($toolCall->function->arguments, true) ?? [];

                $payload['messages'][] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id,
                    'content' => $this->executeTool($toolCall->function->name, $arguments, $profileId),
                ];
            }
        }

        return $lastContent ?: 'I was unable to complete your request. Please try again.';
    }

    private function executeTool(string $toolName, array $arguments, string $profileId): string
    {
        return match ($toolName) {
            'get_today_logs' => $this->getTodayLogs($profileId),
            'get_weekly_summary' => $this->getWeeklySummary($profileId, $arguments['days'] ?? 7),
            'get_user_targets' => $this->getUserTargets($profileId),
            'get_meal_details' => $this->getMealDetails($arguments['meal_post_id']),
            'search_meals' => $this->searchMeals($arguments['query'], $arguments['max_calories'] ?? null, $arguments['min_protein'] ?? null),
            'log_meal' => $this->logMeal($profileId, $arguments['name'], (float) $arguments['calories'], (float) $arguments['protein'], (float) $arguments['carbs'], (float) $arguments['fats'], (float) ($arguments['fiber'] ?? 0)),
            'delete_last_log' => $this->deleteLastLog($profileId),
            default => "Unknown tool: {$toolName}",
        };
    }

    public function getTodayLogs(string $profileId): string
    {
        $profile = UserProfile::findOrFail($profileId);

        $summary = DailySummary::where('user_id', $profile->user_id)
            ->whereDate('date', today())
            ->first();

        if (! $summary) {
            return 'No meals logged today.';
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

        return <<<RESULT
        Today's Log ({$summary->logs_count} meal(s) logged):
        - Calories : {$summary->calories_consumed} / {$summary->calories_target} kcal ({$caloriesRemaining} kcal remaining)
        - Protein  : {$summary->protein_consumed}g / {$summary->protein_target}g ({$proteinRemaining}g remaining)
        - Carbs    : {$summary->carbs_consumed}g / {$summary->carbs_target}g ({$carbsRemaining}g remaining)
        - Fats     : {$summary->fats_consumed}g / {$summary->fats_target}g ({$fatsRemaining}g remaining)

        Meals logged:
        {$mealList}
        RESULT;
    }

    public function getWeeklySummary(string $profileId, int $days = 7): string
    {
        $profile = UserProfile::findOrFail($profileId);

        $summaries = DailySummary::where('user_id', $profile->user_id)
            ->where('date', '>=', today()->subDays($days))
            ->orderBy('date')
            ->get();

        if ($summaries->isEmpty()) {
            return "No data found for the last {$days} days.";
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

        return <<<RESULT
        Weekly Summary (last {$days} days):
        {$dailyLines}

        - Average daily calories : {$avgCalories} kcal
        - Days target hit        : {$daysHit}
        - Days target missed     : {$daysMissed}
        - Best day  (closest to target) : {$bestDay} ({$best?->calories_consumed} / {$best?->calories_target} kcal)
        - Worst day (furthest from target) : {$worstDay} ({$worst?->calories_consumed} / {$worst?->calories_target} kcal)
        RESULT;
    }

    public function getUserTargets(string $profileId): string
    {
        $profile = UserProfile::findOrFail($profileId);

        $fiberTarget = $profile->daily_fiber_g ?? 25;

        return <<<RESULT
        User Targets:
        - Calories     : {$profile->daily_calorie_target} kcal
        - Protein      : {$profile->daily_protein_g}g
        - Carbs        : {$profile->daily_carbs_g}g
        - Fat          : {$profile->daily_fat_g}g
        - Fiber        : {$fiberTarget}g
        - Goal         : {$profile->goal?->value}
        - Activity     : {$profile->activity_level?->value}
        RESULT;
    }

    public function getMealDetails(int $mealPostId): string
    {
        $meal = MealPost::with(['mealMacro', 'ingredients', 'preparationSteps'])
            ->find($mealPostId);

        if (! $meal) {
            return 'Meal not found.';
        }

        $macro = $meal->mealMacro;

        $ingredients = $meal->ingredients->isNotEmpty()
            ? $meal->ingredients->map(fn ($i) => "  - {$i->name_en}: {$i->pivot->portion} {$i->pivot->unit}")->implode("\n")
            : '  Not available';

        $steps = $meal->preparationSteps->isNotEmpty()
            ? $meal->preparationSteps->map(fn ($s) => "  {$s->step_number}. {$s->description}")->implode("\n")
            : '  Not provided';

        return <<<RESULT
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
    }

    public function searchMeals(string $query, ?int $maxCalories = null, ?int $minProtein = null): string
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
            return 'No meals found matching your criteria.';
        }

        $lines = $results->map(fn ($m) => "- [ID:{$m->id}] {$m->name} | {$m->calories} kcal | protein {$m->protein}g | carbs {$m->carbs}g | fats {$m->fats}g"
        )->implode("\n");

        return "Meals matching '{$query}':\n{$lines}";
    }

    public function logMeal(string $profileId, string $name, float $calories, float $protein, float $carbs, float $fats, float $fiber = 0): string
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

            $caloriesRemaining = $summary->calories_target - $summary->calories_consumed;
            $proteinRemaining = $summary->protein_target - $summary->protein_consumed;

            return <<<RESULT
            Logged: {$name}
            - Calories : {$calories} kcal | Protein: {$protein}g | Carbs: {$carbs}g | Fats: {$fats}g | Fiber: {$fiber}g
            - Calories remaining : {$caloriesRemaining} kcal
            - Protein remaining  : {$proteinRemaining}g
            RESULT;
        });
    }

    public function deleteLastLog(string $profileId): string
    {
        return DB::transaction(function () use ($profileId) {
            $profile = UserProfile::findOrFail($profileId);

            $summary = DailySummary::where('user_id', $profile->user_id)
                ->whereDate('date', today())
                ->first();

            if (! $summary) {
                return 'No recent log found to delete.';
            }

            $log = DailyLog::where('daily_summary_id', $summary->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $log) {
                return 'No recent log found to delete.';
            }

            $summary->decrement('calories_consumed', $log->calories);
            $summary->decrement('protein_consumed', $log->protein);
            $summary->decrement('carbs_consumed', $log->carbs);
            $summary->decrement('fats_consumed', $log->fats);
            $summary->decrement('fiber_consumed', $log->fiber);
            $summary->decrement('logs_count');
            $summary->refresh();

            $logName = $log->log_name;
            $logCalories = $log->calories;
            $logProtein = $log->protein;
            $logCarbs = $log->carbs;
            $logFats = $log->fats;
            $caloriesRemaining = $summary->calories_target - $summary->calories_consumed;

            $log->delete();

            return <<<RESULT
            Deleted: {$logName}
            - Removed  : {$logCalories} kcal | protein {$logProtein}g | carbs {$logCarbs}g | fats {$logFats}g
            - Calories remaining : {$caloriesRemaining} kcal
            RESULT;
        });
    }
}
