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

    private const SUMMARIZE_EVERY = 10;

    public function sendMessage(string $message, string $profileId): string
    {
        $userInfo = $this->getUserInfo($profileId);

        $result = DB::transaction(function () use ($message, $profileId, $userInfo) {
            $conversation = $this->findOrCreateConversation($profileId);
            $this->insertMessage($conversation->id, 'user', $message);
            $history = $this->buildHistory($conversation);

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

            return [
                'content' => $aiContent,
                'conversation' => $conversation,
                'count' => ConversationMessages::where('conversation_id', $conversation->id)->count(),
            ];
        });

        // Summarization runs OUTSIDE transaction
        if ($result['count'] % self::SUMMARIZE_EVERY === 0) {
            $this->summarize($result['conversation']);
        }

        return $result['content'];
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

    private function buildHistory(Conversations $conversation): array
    {
        $history = [];

        if ($conversation->summary) {
            $history[] = [
                'role' => 'assistant',
                'content' => "Summary of our earlier conversation: {$conversation->summary}",
            ];
        }

        $messages = ConversationMessages::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(self::CONTEXT_WINDOW)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        return array_merge($history, $messages);
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

    private function summarize(Conversations $conversation): void
    {
        $messages = ConversationMessages::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(self::SUMMARIZE_EVERY)
            ->get()
            ->reverse()
            ->map(fn ($m) => "{$m->role}: {$m->content}")
            ->implode("\n");

        $existingSummary = $conversation->summary
            ? "Previous summary:\n{$conversation->summary}\n\n"
            : '';

        $prompt = <<<PROMPT
    {$existingSummary}
    New messages to incorporate:
    {$messages}

    Create a comprehensive updated summary that captures:
    - All food preferences and dislikes mentioned
    - Health related concerns raised
    - Goals and progress discussed
    - Important decisions or commitments made
    - Patterns in eating behavior noted

    Be concise but complete. Maximum 200 words.
    PROMPT;

        $response = OpenAI::chat()->create([
            'model' => env('OPENAI_MODEL_CLASSIFY'),
            'temperature' => 0,
            'max_completion_tokens' => 300,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $conversation->update([
            'summary' => $response->choices[0]->message->content,
        ]);
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
        Today's intake so far ({$summary['logs_count']} meal(s) logged):
        - Calories : {$summary['calories_consumed']} / {$targets['calories']} kcal ({$summary['calories_remaining']} kcal remaining)
        - Protein  : {$summary['protein_consumed']}g / {$targets['protein_g']}g ({$summary['protein_remaining']}g remaining)
        - Carbs    : {$summary['carbs_consumed']}g / {$targets['carbs_g']}g ({$summary['carbs_remaining']}g remaining)
        - Fat      : {$summary['fats_consumed']}g / {$targets['fat_g']}g ({$summary['fats_remaining']}g remaining)
        - Fiber    : {$summary['fiber_consumed']}g logged
        INTAKE
            : "Today's intake: No food logged yet today.";

        return <<<PROMPT
    You are a personal nutrition assistant for a NutriSphere user.

    === YOUR PURPOSE ===
    You help this specific user with two types of questions:

    1. Personal questions — about this user's intake, macros,
       meals, goals, and health conditions.
       Always use their real data when answering these.

    2. Educational questions — about nutrition, food, diets,
       ingredients, and healthy eating in general.
       Answer these as a knowledgeable nutrition expert.

    === STRICT BOUNDARIES ===
    You do NOT answer:
    - Questions about other specific people's diets
      (celebrities, athletes, public figures)
    - Anything completely unrelated to food and nutrition
    - Creative writing requests (poems, stories, songs)
      even if the topic is food related

    When asked something outside these boundaries respond with:
    "I am your nutrition assistant. I can help with your
    personal nutrition data or any general food and nutrition
    question. What would you like to know?"

    Never make exceptions to this rule.

    === HEALTH CONDITION RULES ===
    The user has the following health conditions: {$conditions}

    When making any food suggestion:
    - Never suggest ingredients that negatively impact
      these conditions — not even as optional additions
    - Apply strict dietary guidelines not loose suggestions
    - If a food is risky for the condition flag it clearly
      and suggest a safe alternative instead
    - Do not say "in moderation" for foods that cause
      direct harm — say avoid and explain why

    === USER PROFILE ===
    - Age            : {$userInfo['age']}
    - Gender         : {$userInfo['gender']}
    - Region         : {$userInfo['region']}
    - Weight         : {$userInfo['weight_kg']} kg
    - Height         : {$userInfo['height_cm']} cm
    - Body fat       : {$userInfo['body_fat_pct']}%
    - Activity level : {$userInfo['activity_level']}
    - Goal           : {$userInfo['goal']}
    - Dietary prefs  : {$userInfo['dietary_preferences']}
    
    === DAILY TARGETS ===
    - Calories : {$targets['calories']} kcal
    - Protein  : {$targets['protein_g']}g
    - Carbs    : {$targets['carbs_g']}g
    - Fat      : {$targets['fat_g']}g

    === TODAY'S INTAKE ===
    {$intakeSection}

    === BEHAVIOR RULES ===
    - Always base personal answers on the user's real data above
    - Never hallucinate numbers — only use data provided
    - Be concise and practical — no long paragraphs
    - Be supportive and encouraging — never judgmental
    - If data is missing say so honestly and ask the user to log more
    - Never provide medical diagnoses or replace medical advice
    - Always recommend consulting a doctor for medical decisions
    - If the user states a food preference or dislike earlier 
    in the conversation remember it for the entire session.
    Never suggest a food the user has said they dislike
    even if it is nutritionally appropriate.
    PROMPT;
    }
}
