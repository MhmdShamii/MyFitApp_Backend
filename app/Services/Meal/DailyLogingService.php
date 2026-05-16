<?php

namespace App\Services\Meal;

use App\Enums\DailyLogType;
use App\Models\DailyLog;
use App\Models\DailySummary;
use App\Models\MealMacro;
use App\Models\MealPost;
use App\Models\User;
use App\Services\Analytics\PlatformStatsService;
use App\Services\ChatRAG\FeedbackRecorderService;
use App\Services\Notification\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyLogingService
{
    public function __construct(
        private CalculateMacrosService $macrosService,
        private NotificationService $notificationService,
        private PlatformStatsService $platformStatsService,
        private FeedbackRecorderService $feedbackRecorder,
    ) {}

    public function logMealFromPost(MealPost $mealPost, User $user): DailyLog
    {
        $log = DB::transaction(function () use ($mealPost, $user) {
            $summary = $this->findOrCreateSummary($user, now()->toDateString(), $user->profile);
            $mealPost->increment('relogs_count');

            $log = $this->createLog($user, $summary, $this->calculateForOnePortion($mealPost), $mealPost->name, null, DailyLogType::MEAL, $mealPost->id);

            $this->notificationService->notifyRelog($user, $mealPost);

            return $log;
        });

        $this->recordFeedback($user, $log, 'post', $mealPost->id);

        return $log;
    }

    public function removeLogFromDailySummary(DailyLog $log): void
    {
        $wasConfirmed = $log->confirmed_at !== null;

        DB::transaction(function () use ($log, $wasConfirmed) {
            if ($wasConfirmed) {
                $summary = $log->dailySummary;
                if ($summary) {
                    $this->modifyDailySummary($summary, $log, isAdding: false);
                }
            }

            if ($log->meal_post_id !== null) {
                MealPost::where('id', $log->meal_post_id)->decrement('relogs_count');
            }

            $log->delete();
        });

        if ($wasConfirmed) {
            $this->platformStatsService->decrementMealsLogged();
        }
    }

    public function logCustomMeal(User $user, array $validatedData): DailyLog
    {
        $log = DB::transaction(function () use ($user, $validatedData) {
            $this->discardExistingDraft($user, DailyLogType::CUSTOM);

            [, $macros] = $this->macrosService->calculateMealMacrosPipeline($validatedData['ingredients']);

            $summary = $this->findOrCreateSummary($user, now()->toDateString(), $user->profile);

            return $this->createPendingLog($user, $summary, $this->macroToArray($macros), data_get($validatedData, 'name'), data_get($validatedData, 'description'), DailyLogType::CUSTOM);
        });

        $this->recordFeedback($user, $log, 'ingredients');

        return $log;
    }

    public function logEstimatedMeal(User $user, array $validatedData): DailyLog
    {
        $log = DB::transaction(function () use ($user, $validatedData) {
            $this->discardExistingDraft($user, DailyLogType::ESTIMATE);

            $country = $user->load('country')->country;

            $macros = $this->macrosService->estimateMacrosPipeline(
                $validatedData['name'],
                data_get($validatedData, 'description'),
                $country?->name ?? 'Unknown',
            );

            $summary = $this->findOrCreateSummary($user, now()->toDateString(), $user->profile);

            return $this->createPendingLog($user, $summary, $this->macroToArray($macros), data_get($validatedData, 'name'), data_get($validatedData, 'description'), DailyLogType::ESTIMATE);
        });

        $this->recordFeedback($user, $log, 'estimate');

        return $log;
    }

    public function confirmPendingLog(DailyLog $log): DailyLog
    {
        return DB::transaction(function () use ($log) {
            $log->update(['confirmed_at' => now()]);

            $this->modifyDailySummary($log->dailySummary, $log, isAdding: true);

            $this->platformStatsService->incrementMealsLogged();

            return $log;
        });
    }

    // helper functions

    private function recordFeedback(User $user, DailyLog $log, string $sourceType, ?int $mealPostId = null): void
    {
        try {
            $this->feedbackRecorder->record(
                profileId: $user->profile->id,
                mealTitle: $log->log_name ?? 'Unknown',
                mealPostId: $mealPostId,
                sourceType: $sourceType,
                calories: (float) $log->calories,
                protein: (float) $log->protein,
                carbs: (float) $log->carbs,
                fats: (float) $log->fats,
                action: 'logged',
            );
        } catch (\Throwable $e) {
            Log::error("Feedback recording failed after {$sourceType} log: " . $e->getMessage());
        }
    }

    private function findOrCreateSummary(User $user, string $date, $profile): DailySummary
    {
        try {
            return DailySummary::firstOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'calories_target' => $profile?->daily_calorie_target ?? 0,
                    'protein_target'  => $profile?->daily_protein_g ?? 0,
                    'carbs_target'    => $profile?->daily_carbs_g ?? 0,
                    'fats_target'     => $profile?->daily_fat_g ?? 0,
                    'fiber_target'    => 0,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            return DailySummary::where('user_id', $user->id)->where('date', $date)->firstOrFail();
        }
    }

    private function calculateForOnePortion(MealPost $mealPost): array
    {
        $macro    = $mealPost->mealMacro;
        $servings = $mealPost->servings ?: 1;

        return [
            'calories' => ($macro?->calories ?? 0) / $servings,
            'protein'  => ($macro?->protein ?? 0) / $servings,
            'carbs'    => ($macro?->carbs ?? 0) / $servings,
            'fats'     => ($macro?->fats ?? 0) / $servings,
            'fiber'    => ($macro?->fiber ?? 0) / $servings,
        ];
    }

    private function macroToArray(MealMacro $macro): array
    {
        return [
            'calories' => $macro->calories,
            'protein'  => $macro->protein,
            'carbs'    => $macro->carbs,
            'fats'     => $macro->fats,
            'fiber'    => $macro->fiber,
        ];
    }

    private function createLog(User $user, DailySummary $summary, array $macros, ?string $name, ?string $description, DailyLogType $type, ?int $mealPostId = null): DailyLog
    {
        $log = DailyLog::create([
            'user_id'          => $user->id,
            'daily_summary_id' => $summary->id,
            'logged_at'        => now(),
            'confirmed_at'     => now(),
            'type'             => $type,
            'meal_post_id'     => $mealPostId,
            'log_name'         => $name,
            'description'      => $description,
            'calories'         => $macros['calories'],
            'protein'          => $macros['protein'],
            'carbs'            => $macros['carbs'],
            'fats'             => $macros['fats'],
            'fiber'            => $macros['fiber'],
        ]);

        $this->modifyDailySummary($summary, $log, isAdding: true);
        $this->platformStatsService->incrementMealsLogged();

        return $log;
    }

    private function createPendingLog(User $user, DailySummary $summary, array $macros, ?string $name, ?string $description, DailyLogType $type): DailyLog
    {
        return DailyLog::create([
            'user_id'          => $user->id,
            'daily_summary_id' => $summary->id,
            'logged_at'        => now(),
            'confirmed_at'     => null,
            'type'             => $type,
            'log_name'         => $name,
            'description'      => $description,
            'calories'         => $macros['calories'],
            'protein'          => $macros['protein'],
            'carbs'            => $macros['carbs'],
            'fats'             => $macros['fats'],
            'fiber'            => $macros['fiber'],
        ]);
    }

    private function discardExistingDraft(User $user, DailyLogType $type): void
    {
        DailyLog::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('confirmed_at')
            ->delete();
    }

    private function modifyDailySummary(DailySummary $summary, DailyLog $log, bool $isAdding = true): void
    {
        if ($isAdding) {
            $summary->increment('calories_consumed', $log->calories);
            $summary->increment('protein_consumed', $log->protein);
            $summary->increment('carbs_consumed', $log->carbs);
            $summary->increment('fats_consumed', $log->fats);
            $summary->increment('fiber_consumed', $log->fiber);
            $summary->increment('logs_count', 1);
        } else {
            $summary->decrement('calories_consumed', $log->calories);
            $summary->decrement('protein_consumed', $log->protein);
            $summary->decrement('carbs_consumed', $log->carbs);
            $summary->decrement('fats_consumed', $log->fats);
            $summary->decrement('fiber_consumed', $log->fiber);
            $summary->decrement('logs_count', 1);
        }
    }
}
