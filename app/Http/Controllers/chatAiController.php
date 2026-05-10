<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class chatAiController extends Controller
{
    public function sendMessage(){
        return response()->json(['message' => 'Message sent to AI']);
    }
    
    public function getMessageHistory(){
        return response()->json(['message' => 'Message history retrieved']);
    }
}
