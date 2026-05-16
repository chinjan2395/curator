<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\SyncLog;
use Illuminate\Http\Request;

class UserSyncSummaryController extends Controller
{
    private const SCHEDULER_TRIGGERS = ['scheduler', 'queue'];

    public function show(Request $request)
    {
        $user = $request->user();
        $lastLoginAt = $user->last_login_at;
        $seenAt = $user->sync_notifications_seen_at;

        $newPostCount = 0;
        if ($lastLoginAt) {
            $newPostCount = Post::whereHas('feed.workspace', fn($q) => $q->where('owner_id', $user->id))
                ->where('created_at', '>', $lastLoginAt)
                ->count();
        }

        $schedulerPostsSinceLogin = $this->schedulerPostsAfter($user->id, $lastLoginAt);
        $unreadSince = $seenAt ?: $lastLoginAt;
        $schedulerUnreadCount = $this->schedulerPostsAfter($user->id, $unreadSince);

        $brokenCredentials = SocialCredential::where('user_id', $user->id)
            ->where('status', '!=', 'active')
            ->get(['id', 'provider', 'account_label', 'account_id', 'status']);

        return response()->json([
            'new_post_count' => $newPostCount,
            'scheduler_synced_post_count' => $schedulerPostsSinceLogin,
            'scheduler_unread_count' => $schedulerUnreadCount,
            'sync_notifications_seen_at' => $seenAt?->toIso8601String(),
            'broken_credentials' => $brokenCredentials,
        ]);
    }

    public function acknowledge(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['sync_notifications_seen_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'sync_notifications_seen_at' => $user->sync_notifications_seen_at?->toIso8601String(),
        ]);
    }

    private function schedulerPostsAfter(int $userId, $after): int
    {
        if (! $after) {
            return 0;
        }

        return (int) SyncLog::query()
            ->where('user_id', $userId)
            ->whereIn('triggered_by', self::SCHEDULER_TRIGGERS)
            ->where('status', 'success')
            ->where('posts_synced', '>', 0)
            ->where('created_at', '>', $after)
            ->sum('posts_synced');
    }
}
