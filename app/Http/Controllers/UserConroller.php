<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserConroller extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user(), 200);
    }
}
