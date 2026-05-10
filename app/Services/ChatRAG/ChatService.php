<?php

namespace App\Services\ChatRAG;

use App\Models\ConversationMessages;
use App\Models\Conversations;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;

class ChatService
{
    private const CONTEXT_WINDOW = 10;

    public function sendMessage(string $message, string $profileId): string
    {
        return DB::Transaction(function () use ($message, $profileId) {

            $conversation = $this->findOrCreateConversation($profileId);
            $this->insertMessage($conversation->id, 'user', $message);

            $profile = UserProfile::findOrFail($profileId);
            $history = $this->buildHistory($conversation->id);

            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL_STAGE2', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->buildSystemPrompt($profile)],
                    ...$history,
                ],
            ]);

            $aiContent = $response->choices[0]->message->content;
            $this->insertMessage($conversation->id, 'assistant', $aiContent);

            $conversation->update(['last_active_at' => now()]);

            return $aiContent;
        });
    }

    public function getMessageHistory(): array
    {
        return [];
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

    private function buildSystemPrompt(UserProfile $profile): string
    {
        return <<<PROMPT
        You are a personal nutrition assistant for a NutriSphere user.

        User profile:
        - Goal: {$profile->goal?->value}
        - Activity level: {$profile->activity_level?->value}
        - Daily calorie target: {$profile->daily_calorie_target} kcal
        - Protein target: {$profile->daily_protein_g}g | Carbs: {$profile->daily_carbs_g}g | Fat: {$profile->daily_fat_g}g

        Answer questions about nutrition, meal planning, and health goals based on this profile.
        Be concise and practical. Never provide medical diagnoses.
        PROMPT;
    }
}
