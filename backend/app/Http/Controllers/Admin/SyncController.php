<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AdminResyncCredentialJob;
use App\Jobs\AdminSyncFeedJob;
use App\Jobs\AdminSyncRunAllJob;
use App\Models\Feed;
use App\Models\SocialCredential;
use App\Models\SyncLog;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function status()
    {
        $lastLog = SyncLog::whereIn('triggered_by', ['scheduler', 'queue', 'admin'])->latest()->first();
        $lastRunAt = $lastLog?->created_at;
        $nextRunAt = $lastRunAt?->copy()->addMinutes(15);

        return response()->json([
            'last_run_at'  => $lastRunAt?->toIso8601String(),
            'next_run_at'  => $nextRunAt?->toIso8601String(),
            'total_feeds'  => Feed::count(),
            'broken_count' => SocialCredential::where('status', '!=', 'active')->count(),
            'logs_today'   => SyncLog::whereDate('created_at', today())->count(),
        ]);
    }

    public function logs(Request $request)
    {
        $query = SyncLog::with(['user'])->latest();

        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('triggered_by')) {
            $query->where('triggered_by', $request->input('triggered_by'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json($query->paginate(15));
    }

    public function brokenCredentials()
    {
        $credentials = SocialCredential::with('user')
            ->where('status', '!=', 'active')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($credentials);
    }

    public function runAll(Request $request): JsonResponse
    {
        $admin = $request->user();
        ActivityLogger::log($admin, 'feed.sync_all', 'Triggered sync for all feeds (admin)');

        AdminSyncRunAllJob::dispatch((int) $admin->id);

        return response()->json([
            'message' => 'Sync started',
            'queued' => true,
            'triggered_at' => now()->toIso8601String(),
        ], 202);
    }

    public function resyncCredential(Request $request, SocialCredential $credential): JsonResponse
    {
        ActivityLogger::log(
            $request->user(),
            'feed.resync_credential',
            "Queued re-sync for credential \"{$credential->account_label}\" (admin)",
            'credential',
            $credential->id,
            $credential->account_label,
        );

        AdminResyncCredentialJob::dispatch((int) $request->user()->id, $credential->id);

        return response()->json([
            'queued' => true,
            'credential_id' => $credential->id,
            'message' => 'Credential re-sync started.',
        ], 202);
    }

    public function syncFeed(Request $request, Feed $feed): JsonResponse
    {
        AdminSyncFeedJob::dispatch((int) $request->user()->id, $feed->id);

        ActivityLogger::log(
            $request->user(),
            'feed.synced',
            "Queued sync for {$feed->type} feed \"{$feed->name}\" (admin)",
            'feed',
            $feed->id,
            $feed->name,
        );

        return response()->json([
            'queued' => true,
            'feed_id' => $feed->id,
            'message' => 'Feed sync started.',
        ], 202);
    }
}
