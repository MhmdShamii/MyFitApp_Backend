<?php

namespace App\Services\ChatRAG;

use App\Models\ConversationMessages;
use App\Models\Conversations;
use App\Models\DailySummary;
use App\Models\UserProfile;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class ChatService
{
    private const CONTEXT_WINDOW = 10;

    public function sendMessage(string $message, string $profileId): string
    {
        $userInfo = $this->getUserInfo($profileId);

        return DB::Transaction(function () use ($message, $profileId, $userInfo) {

            $conversation = $this->findOrCreateConversation($profileId);
            $this->insertMessage($conversation->id, 'user', $message);

            $history = $this->buildHistory($conversation->id);

            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL_CHAT', 'gpt-4o-2024-08-06'),
                'max_completion_tokens' => 500,
                'messages' => [
                    ['role' => 'system', 'content' => $this->buildSystemPrompt($userInfo)],
                    ...$history,
                ],
            ]);

            $aiContent = $response->choices[0]->message->content;
            $this->insertMessage($conversation->id, 'assistant', $aiContent);

            $conversation->update(['last_active_at' => now()]);

            return $aiContent;
        });
    }

    public function getMessageHistory(string $profileId, int $perPage = 20): CursorPaginator
    {
        $conversation = Conversations::where('profile_id', $profileId)->first();

        return ConversationMessages::where('conversation_id', $conversation?->id ?? 0)
            ->orderBy('id', 'desc')
            ->cursorPaginate($perPage);
    }

    private function getUserInfo(string $profileId): array
    {
        $profile = UserProfile::with(['user.country', 'user.healthConditions.condition'])
            ->findOrFail($profileId);

        $user = $profile->user;

        $age = $profile->date_of_birth
            ? $profile->date_of_birth->age
            : null;

        $healthConditions = $user->healthConditions
            ->map(fn ($uc) => $uc->condition?->name ?? $uc->custom_condition)
            ->filter()
            ->values()
            ->toArray();

        return [
            'age' => $age,
            'gender' => $profile->gender?->value,
            'region' => $user->country?->name,
            'weight_kg' => $profile->weight_kg,
            'height_cm' => $profile->height_cm,
            'body_fat_pct' => $profile->body_fat_pct,
            'activity_level' => $profile->activity_level?->value,
            'goal' => $profile->goal?->value,
            'dietary_preferences' => $profile->dietary_preferences?->value,
            'targets' => [
                'calories' => $profile->daily_calorie_target,
                'protein_g' => $profile->daily_protein_g,
                'carbs_g' => $profile->daily_carbs_g,
                'fat_g' => $profile->daily_fat_g,
            ],
            'health_conditions' => $healthConditions,
            'daily_summary' => $this->getDailySummary($profile),
        ];
    }

    private function getDailySummary(UserProfile $profile): ?array
    {
        $summary = DailySummary::where('user_id', $profile->user_id)
            ->whereDate('date', today())
            ->first();

        if (! $summary) {
            return null;
        }

        return [
            'calories_consumed' => $summary->calories_consumed,
            'protein_consumed' => $summary->protein_consumed,
            'carbs_consumed' => $summary->carbs_consumed,
            'fats_consumed' => $summary->fats_consumed,
            'fiber_consumed' => $summary->fiber_consumed,
            'logs_count' => $summary->logs_count,
            'calories_remaining' => $profile->daily_calorie_target - $summary->calories_consumed,
            'protein_remaining' => $profile->daily_protein_g - $summary->protein_consumed,
            'carbs_remaining' => $profile->daily_carbs_g - $summary->carbs_consumed,
            'fats_remaining' => $profile->daily_fat_g - $summary->fats_consumed,
        ];
    }

    private function buildHistory(int $conversationId): array
    {
        return ConversationMessages::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit(self::CONTEXT_WINDOW)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();
    }

    private function insertMessage(string $conversationId, string $role, string $content): void
    {
        ConversationMessages::create([
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
        ]);
    }

    private function findOrCreateConversation(string $profileId): Conversations
    {
        $conversation = Conversations::where('profile_id', $profileId)->firstOrNew(['profile_id' => $profileId]);
        if (! $conversation->exists) {
            $conversation->save();
        }

        return $conversation;
    }

    private function buildSystemPrompt(array $userInfo): string
    {
        $conditions = ! empty($userInfo['health_conditions'])
            ? implode(', ', $userInfo['health_conditions'])
            : 'None reported';

        $targets = $userInfo['targets'];
        $summary = $userInfo['daily_summary'];

        $intakeSection = $summary
            ? <<<INTAKE

            Today's intake so far ({$summary['logs_count']} log(s)):
            - Calories:  {$summary['calories_consumed']} / {$targets['calories']} kcal  ({$summary['calories_remaining']} remaining)
            - Protein:   {$summary['protein_consumed']}g / {$targets['protein_g']}g  ({$summary['protein_remaining']}g remaining)
            - Carbs:     {$summary['carbs_consumed']}g / {$targets['carbs_g']}g  ({$summary['carbs_remaining']}g remaining)
            - Fat:       {$summary['fats_consumed']}g / {$targets['fat_g']}g  ({$summary['fats_remaining']}g remaining)
            - Fiber:     {$summary['fiber_consumed']}g logged
            INTAKE
            : "\n        Today's intake: No food logged yet today.";

        return <<<PROMPT
        You are a personal nutrition assistant for a NutriSphere user.

        User profile:
        - Age: {$userInfo['age']}
        - Gender: {$userInfo['gender']}
        - Region: {$userInfo['region']}
        - Weight: {$userInfo['weight_kg']} kg | Height: {$userInfo['height_cm']} cm | Body fat: {$userInfo['body_fat_pct']}%
        - Activity level: {$userInfo['activity_level']}
        - Goal: {$userInfo['goal']}
        - Dietary preferences: {$userInfo['dietary_preferences']}
        - Health conditions: {$conditions}

        Daily targets:
        - Calories: {$targets['calories']} kcal
        - Protein: {$targets['protein_g']}g | Carbs: {$targets['carbs_g']}g | Fat: {$targets['fat_g']}g
        {$intakeSection}
        Answer questions about nutrition, meal planning, and health goals based on this profile.
        Be concise and practical. Never provide medical diagnoses.
        PROMPT;
    }
}
