<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialCredential;
use Illuminate\Http\Request;

class UserSyncSummaryController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $lastLoginAt = $user->last_login_at;

        $newPostCount = 0;
        if ($lastLoginAt) {
            $newPostCount = Post::whereHas('feed.workspace', fn($q) => $q->where('owner_id', $user->id))
                ->where('created_at', '>', $lastLoginAt)
                ->count();
        }

        $brokenCredentials = SocialCredential::where('user_id', $user->id)
            ->where('status', '!=', 'active')
            ->get(['id', 'provider', 'account_label', 'account_id', 'status']);

        return response()->json([
            'new_post_count' => $newPostCount,
            'broken_credentials' => $brokenCredentials,
        ]);
    }
}
