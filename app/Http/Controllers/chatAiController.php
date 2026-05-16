<?php

namespace App\Http\Controllers;

use App\Http\Requests\sendMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ChatRAG\ChatService;
use Illuminate\Http\Request;

class ChatAiController extends Controller
{
    use ApiResponse;

    public function __construct(private ChatService $chatService) {}

    public function sendMessage(sendMessageRequest $request)
    {
        $response = $this->chatService->sendMessage($request->input('message'), Auth()->user()->profile->id);

        return response()->json([
            ...$response,
            'status' => 'success',
        ]);
    }

    public function getMessageHistory(Request $request)
    {
        $paginator = $this->chatService->getMessageHistory(
            auth()->user()->profile->id,
            $request->integer('per_page', 20),
        );

        return $this->paginated(
            $paginator->items(),
            [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more'    => $paginator->hasMorePages(),
            ],
            'Message history retrieved successfully',
        );
    }
}
