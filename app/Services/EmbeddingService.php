<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    public function generate(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dot = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;

        foreach ($vec1 as $i => $val) {
            $dot  += $val * $vec2[$i];
            $mag1 += $val * $val;
            $mag2 += $vec2[$i] * $vec2[$i];
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        if ($mag1 === 0.0 || $mag2 === 0.0) {
            return 0.0;
        }

        return $dot / ($mag1 * $mag2);
    }

    public function averageVectors(array $vectors): array
    {
        if (empty($vectors)) {
            return [];
        }

        $count = count($vectors);
        $sum   = array_fill(0, count($vectors[0]), 0.0);

        foreach ($vectors as $vector) {
            foreach ($vector as $i => $val) {
                $sum[$i] += $val;
            }
        }

        return array_map(fn($v) => $v / $count, $sum);
    }
}
