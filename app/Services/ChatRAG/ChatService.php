<?php

namespace App\Services\ChatRAG;

use App\Models\Conversations;

class ChatService
{
    public function sendMessage(string $message, string $profileId): string
    {
        return $this->findOrCreateConversation($profileId);
    }

    public function getMessageHistory(): array
    {
        return [];
    }

    private function findOrCreateConversation(string $profileId): string
    {
        $conversation = Conversations::where('profile_id', $profileId)->firstOrNew(['profile_id' => $profileId]);
        if (!$conversation->exists) {
            $conversation->save();
        }
        return $conversation->id;
    }
}