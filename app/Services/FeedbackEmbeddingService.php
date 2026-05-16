<?php

namespace App\Services;

use App\Enums\FeedbackSource;
use OpenAI\Laravel\Facades\OpenAI;

class FeedbackEmbeddingService
{
    public function insertFeedback(FeedbackSource $source, string $text): void
    {
        $embedding = $this->generateEmbedding($text);

    }

    private function generateEmbedding(string $text): array
    {
        try {

            $response = OpenAI::embeddings()->create([
                'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
                'input' => $text,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error generating embedding: '.$e->getMessage());

            return null;
        }

        return $response->embeddings[0]->embedding ?? null;
    }
}
