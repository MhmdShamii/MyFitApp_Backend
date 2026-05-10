<?php

namespace App\Http\Controllers;

use App\Http\Requests\sendMessageRequest;
use App\Services\ChatRAG\ChatService;
use App\Http\Responses\ApiResponse;

class ChatAiController extends Controller
{
    use ApiResponse;

    public function __construct(private ChatService $chatService) {}

    public function sendMessage(sendMessageRequest $request)
    {   
        $response = $this->chatService->sendMessage($request->input('message'));
        return $this->success($response, 'Message sent successfully', dataKey: 'chat_response');
    }

    public function getMessageHistory()
    {
        $history = $this->chatService->getMessageHistory();
        return $this->success($history, 'Message history retrieved successfully', dataKey: 'chat_history');
    }
}
