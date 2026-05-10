<?php

namespace App\Services\ChatRAG;

class chatService
{
    public function sendMessage($message){
        return "AI response to: " . $message;
    }

    public function getMessageHistory(){
        return "Message history retrieved";
    }
}