<?php

namespace App\Services\ChatRAG;

use OpenAI\Laravel\Facades\OpenAI;

class AgenticToolsLayerService
{
    use NutritionToolsTrait;

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

            $payload['messages'][] = [
                'role' => 'assistant',
                'content' => $choice->message->content,
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => ['name' => $tc->function->name, 'arguments' => $tc->function->arguments],
                ], $choice->message->toolCalls),
            ];

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
        $result = match ($toolName) {
            'get_today_logs' => $this->getTodayLogs($profileId),
            'get_weekly_summary' => $this->getWeeklySummary($profileId, $arguments['days'] ?? 7),
            'get_user_targets' => $this->getUserTargets($profileId),
            'get_meal_details' => $this->getMealDetails($arguments['meal_post_id']),
            'search_meals' => $this->searchMeals($arguments['query'], $arguments['max_calories'] ?? null, $arguments['min_protein'] ?? null),
            'log_meal' => $this->logMeal($profileId, $arguments['name'], (float) $arguments['calories'], (float) $arguments['protein'], (float) $arguments['carbs'], (float) $arguments['fats'], (float) ($arguments['fiber'] ?? 0)),
            'delete_last_log' => $this->deleteLastLog($profileId),
            default => ['text' => "Unknown tool: {$toolName}", 'data' => null],
        };

        return $result['text'];
    }

    private function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_today_logs',
                    'description' => 'Get meals logged today with macros consumed and remaining targets',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weekly_summary',
                    'description' => 'Get nutrition summary for last N days including average calories days on target and patterns',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['days' => ['type' => 'integer', 'description' => 'Number of days to look back default 7']],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_user_targets',
                    'description' => 'Get the user daily macro targets goal and activity level',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_meal_details',
                    'description' => 'Get full details of a specific meal post including ingredients and preparation steps',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['meal_post_id' => ['type' => 'integer', 'description' => 'The ID of the meal post to retrieve']],
                        'required' => ['meal_post_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_meals',
                    'description' => 'Search community meal posts by name with optional macro filters. Use this when user asks for meal suggestions or recommendations.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Meal name or description to search for'],
                            'max_calories' => ['type' => 'integer', 'description' => 'Maximum calories filter optional'],
                            'min_protein' => ['type' => 'integer', 'description' => 'Minimum protein in grams filter optional'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'log_meal',
                    'description' => 'Log a meal to today intake. Only call after user explicitly approves. If bot suggested the meal log immediately on approval. If bot estimated from description confirm macros with user first.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Name of the meal'],
                            'calories' => ['type' => 'number', 'description' => 'Calories in kcal'],
                            'protein' => ['type' => 'number', 'description' => 'Protein in grams'],
                            'carbs' => ['type' => 'number', 'description' => 'Carbohydrates in grams'],
                            'fats' => ['type' => 'number', 'description' => 'Fats in grams'],
                            'fiber' => ['type' => 'number', 'description' => 'Fiber in grams optional defaults to 0'],
                        ],
                        'required' => ['name', 'calories', 'protein', 'carbs', 'fats'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_last_log',
                    'description' => 'Delete the most recently logged meal today. Only call when user explicitly asks to undo cancel or remove their last log.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
                ],
            ],
        ];
    }
}
