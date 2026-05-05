<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        ActivityLogger::log($request->user(), 'auth.logout', 'Logged out');

        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
