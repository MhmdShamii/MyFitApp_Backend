<?php

namespace App\Http\Controllers;

use App\Http\Requests\sendMessageRequest;
use App\Services\ChatRAG\chatService;

class chatAiController extends Controller
{
    public function __construct(private chatService $chatService){}

    public function sendMessage(sendMessageRequest $request){
        $response = $this->chatService->sendMessage($request->input('message'));
        return response()->json(['message' => $response]);
    }

    public function getMessageHistory(){
        $response = $this->chatService->getMessageHistory();
        return response()->json(['message' => $response]);
    }
}
