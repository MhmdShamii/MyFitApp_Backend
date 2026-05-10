<?php

namespace App\Http\Controllers;

use App\Http\Requests\sendMessageRequest;

class chatAiController extends Controller
{
    public function sendMessage(sendMessageRequest $request){
        return response()->json(['message' => $request->input('message')]);
    }

    public function getMessageHistory(){
        return response()->json(['message' => 'Message history retrieved']);
    }
}
