<?php

namespace App\Services\ChatRAG;

use App\Models\UserMemory;
use OpenAI\Laravel\Facades\OpenAI;

class MemoryLayerService
{
    public function extractPrefrencesFromMessage(string $profileId, string $message): array
    {
        $prompt = <<<PROMPT
        Extract food preferences (likes and dislikes) from the user message below.
        Return ONLY a JSON array. No markdown, no explanation.
        Format: [{"key": "food_name", "value": "likes" or "dislikes: reason if given"}]
        If no food preferences are found, return an empty array: []

        Message: "$message"
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

    public function updateUserPrefrences(string $profileId, array $preferences): void
    {
        foreach ($preferences as $preference) {
            $this->createOrUpdateUserPreferences($profileId, $preference);
        }
    }

    private function createOrUpdateUserPreferences(string $profileId, array $preference): void
    {
        UserMemory::updateOrCreate(
            ['profile_id' => $profileId, 'key' => $preference['key']],
            ['value' => $preference['value']]
        );
    }
}
