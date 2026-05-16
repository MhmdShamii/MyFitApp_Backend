<?php

namespace App\Services\ChatRAG;

use OpenAI\Laravel\Facades\OpenAI;

class AgenticToolsLayerService
{
    use NutritionToolsTrait;

    private string $lastToolCalled = '';

    private array $lastToolData = [];

    public function run(array $messages, string $profileId): array
    {
        $this->lastToolCalled = '';
        $this->lastToolData = [];

        $payload = [
            'model' => env('OPENAI_MODEL_CHAT', 'gpt-4o-2024-08-06'),
            'max_completion_tokens' => 1000,
            'tools' => $this->getToolDefinitions(),
            'tool_choice' => 'auto',
            'messages' => $messages,
        ];

        $lastContent = '';

        try {
            for ($i = 0; $i < 5; $i++) {
                $response = OpenAI::chat()->create($payload);
                $choice = $response->choices[0];
                $lastContent = $choice->message->content ?? $lastContent;

                if ($choice->finishReason !== 'tool_calls') {
                    return $this->buildStructuredResponse($choice->message->content ?? '');
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
        } catch (\Exception $e) {
            \Log::error('AgentLoop failed: '.$e->getMessage());

            return [
                'type'    => 'text',
                'message' => 'Something went wrong. Please try again.',
            ];
        }

        return $this->buildStructuredResponse($lastContent ?: '');
    }

    private function executeTool(string $toolName, array $arguments, string $profileId): string
    {
        $this->lastToolCalled = $toolName;

        $result = match ($toolName) {
            'get_today_logs' => $this->getTodayLogs($profileId),
            'get_weekly_summary' => $this->getWeeklySummary($profileId, $arguments['days'] ?? 7),
            'get_user_targets' => $this->getUserTargets($profileId),
            'get_meal_details' => $this->getMealDetails($arguments['meal_post_id']),
            'search_meals' => $this->searchMeals($arguments['query'], $arguments['max_calories'] ?? null, $arguments['min_protein'] ?? null),
            'log_meal' => $this->logMeal($profileId, $arguments['name'], (float) $arguments['calories'], (float) $arguments['protein'], (float) $arguments['carbs'], (float) $arguments['fats'], (float) ($arguments['fiber'] ?? 0)),
            'delete_last_log' => $this->deleteLastLog($profileId),
            'update_daily_targets' => $this->updateDailyTargets(
                $profileId,
                isset($arguments['calories']) ? (int) $arguments['calories'] : null,
                isset($arguments['protein']) ? (int) $arguments['protein'] : null,
                isset($arguments['carbs']) ? (int) $arguments['carbs'] : null,
                isset($arguments['fat']) ? (int) $arguments['fat'] : null,
            ),
            default => ['text' => "Unknown tool: {$toolName}", 'data' => null],
        };

        $this->lastToolData = $result['data'] ?? [];

        return $result['text'];
    }

    private function buildStructuredResponse(string $content): array
    {
        if ($this->lastToolCalled === 'log_meal' && ! empty($this->lastToolData)) {
            return [
                'type' => 'meal_logged',
                'message' => 'Done! '.$this->lastToolData['meal_name'].' has been logged.',
                'log' => [
                    'meal_name' => $this->lastToolData['meal_name'],
                    'calories' => $this->lastToolData['calories'],
                    'protein' => $this->lastToolData['protein'],
                    'carbs' => $this->lastToolData['carbs'],
                    'fats' => $this->lastToolData['fats'],
                    'fiber' => $this->lastToolData['fiber'],
                    'calories_remaining' => $this->lastToolData['calories_remaining'],
                    'protein_remaining' => $this->lastToolData['protein_remaining'],
                ],
            ];
        }

        if ($this->lastToolCalled === 'update_daily_targets' && ! empty($this->lastToolData)) {
            return [
                'type' => 'targets_updated',
                'message' => 'Your daily targets have been updated.',
                'targets' => $this->lastToolData,
            ];
        }

        if ($this->lastToolCalled === 'delete_last_log' && ! empty($this->lastToolData)) {
            return [
                'type' => 'meal_deleted',
                'message' => $this->lastToolData['meal_name'].' has been removed.',
                'log' => [
                    'meal_name' => $this->lastToolData['meal_name'],
                    'calories_removed' => $this->lastToolData['calories_removed'],
                    'calories_remaining' => $this->lastToolData['calories_remaining'],
                ],
            ];
        }

        $stripped = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $stripped = preg_replace('/\s*```$/', '', $stripped);

        $parsed = json_decode($stripped, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($parsed['type'])) {
            $message = $parsed['message'] ?? '';
            if (
                $parsed['type'] === 'text'
                && is_string($message)
                && strlen($message) > 0
                && ($message[0] === '{' || $message[0] === '[')
            ) {
                $inner = json_decode($message, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($inner['type'])) {
                    return $inner;
                }
            }

            return $parsed;
        }

        return [
            'type' => 'text',
            'message' => $content,
        ];
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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_daily_targets',
                    'description' => 'Update the user daily nutrition targets. Only call when the user explicitly asks to change set or update their calorie protein carb or fat targets. All parameters are optional — only pass the ones the user wants to change.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'calories' => ['type' => 'integer', 'description' => 'New daily calorie target in kcal'],
                            'protein' => ['type' => 'integer', 'description' => 'New daily protein target in grams'],
                            'carbs' => ['type' => 'integer', 'description' => 'New daily carbohydrate target in grams'],
                            'fat' => ['type' => 'integer', 'description' => 'New daily fat target in grams'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }
}
