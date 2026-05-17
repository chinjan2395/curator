<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feed;
use App\Models\SocialCredential;
use App\Models\SyncLog;
use App\Services\FeedSyncService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private FeedSyncService $syncService) {}

    public function status()
    {
        $lastLog = SyncLog::where('triggered_by', 'scheduler')->latest()->first();
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

    public function runAll(Request $request)
    {
        $admin = $request->user();
        ActivityLogger::log($admin, 'feed.sync_all', 'Triggered sync for all feeds (admin)');

        app()->terminating(function () {
            $service = app(FeedSyncService::class);
            Feed::with(['socialCredential', 'workspace'])->chunk(50, function ($feeds) use ($service) {
                foreach ($feeds as $feed) {
                    $service->syncFeed($feed, 'admin');
                }
            });
        });

        return response()->json(['message' => 'Sync started', 'triggered_at' => now()->toIso8601String()]);
    }

    public function resyncCredential(Request $request, SocialCredential $credential)
    {
        ActivityLogger::log($request->user(), 'feed.resync_credential', "Re-synced all feeds for credential \"{$credential->account_label}\" (admin)", 'credential', $credential->id, $credential->account_label);

        // Reset to active before attempting sync so transient failures don't keep it disconnected.
        // The sync will re-mark it disconnected only on confirmed revocation (invalid_grant).
        $credential->update(['status' => 'active']);

        // Reload feeds after the reset so eager-loaded socialCredential has fresh status.
        $feeds = Feed::where('social_credential_id', $credential->id)
            ->with(['socialCredential', 'workspace'])
            ->get();

        $results = [];
        foreach ($feeds as $feed) {
            $result = $this->syncService->syncFeed($feed, 'admin');
            $results[] = [
                'feed_id'   => $feed->id,
                'feed_name' => $feed->name ?? $feed->type,
                'synced'    => $result !== null,
            ];
        }

        $credential->refresh();

        return response()->json([
            'status'  => $credential->status,
            'results' => $results,
            'feeds_found' => $feeds->count(),
        ]);
    }

    public function syncFeed(Request $request, Feed $feed)
    {
        $feed->load(['socialCredential', 'workspace']);
        $result = $this->syncService->syncFeed($feed, 'admin');

        if ($result === null) {
            return response()->json(['message' => 'Credential expired or revoked. Reconnect in Credentials.'], 422);
        }

        ActivityLogger::log($request->user(), 'feed.synced', "Synced {$feed->type} feed \"{$feed->name}\" (admin)", 'feed', $feed->id, $feed->name);

        return $result;
    }
}
