<?php

namespace App\Services\ChatRAG;

use App\Models\UserMemory;
use OpenAI\Laravel\Facades\OpenAI;

class MemoryLayerService
{
    public function extractPrefrencesFromMessage(string $profileId, string $message): array
    {
        $prompt = <<<PROMPT
        Extract explicit food preferences from the message.

        ONLY extract preferences the user clearly stated.
        Do NOT infer preferences they didn't mention.

        Always correct any spelling mistakes in the food name before storing.
        Use the standard English spelling of the ingredient.
        For example: "zuccini" → "zucchini", "brocoli" → "broccoli".

        Return ONLY valid JSON array. No markdown, explanation, or extra text.

        Format: [{"key": "food_name", "value": "likes"}, {"key": "food_name", "value": "dislikes"}]

        Examples of what TO extract:
        - "I hate fish" → [{"key": "fish", "value": "dislikes"}]
        - "I love spicy food" → [{"key": "spicy food", "value": "likes"}]
        - "I'm vegetarian" → [{"key": "meat", "value": "dislikes"}]

        Examples of what NOT to extract:
        - "I ate a lot" → [] (not a preference)
        - "Food was tasty" → [] (not a specific preference)
        - "Maybe I don't like pasta" → [] (not explicit enough)

        If no preferences found, return: []

        Message: "{$message}"
        PROMPT;

        $response = OpenAI::chat()->create([
            'model' => env('OPENAI_MODEL_CLASSIFY'),
            'temperature' => 0,
            'max_completion_tokens' => 300,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $preferences = json_decode($response->choices[0]->message->content, true) ?? [];

        if (! empty($preferences)) {
            $this->updateUserPrefrences($profileId, $preferences);
        }

        return $preferences;
    }

    public function getUserPrefrences(string $profileId): array
    {
        return UserMemory::where('profile_id', $profileId)
            ->get()
            ->map(function ($memory) {
                return ['key' => $memory->key, 'value' => $memory->value];
            })
            ->toArray();
    }

    public function updateUserPrefrences(string $profileId, array $preferences): void
    {
        foreach ($preferences as $preference) {
            UserMemory::updateOrCreate(
                ['profile_id' => $profileId, 'key' => $preference['key']],
                ['value' => $preference['value']]
            );
        }
    }
}
