<?php

namespace App\Services;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SyncLog;
use App\Services\DuplicateDetectionService;
use App\Support\ActivityLogger;
use App\Sync\FacebookSyncer;
use App\Sync\InstagramSyncer;
use App\Sync\RssSyncer;
use App\Sync\ThreadsSyncer;
use App\Sync\TikTokSyncer;
use App\Sync\TwitterSyncer;
use App\Sync\YouTubeSyncer;
use Illuminate\Http\JsonResponse;

class FeedSyncService
{
    public function __construct(
        private YouTubeSyncer $youtube,
        private FacebookSyncer $facebook,
        private InstagramSyncer $instagram,
        private TwitterSyncer $twitter,
        private TikTokSyncer $tiktok,
        private ThreadsSyncer $threads,
        private RssSyncer $rss,
    ) {}

    /**
     * Sync one feed. Returns JsonResponse from the syncer on success, or null on auth/unknown-type failure.
     * Marks the credential as 'disconnected' if token refresh fails.
     * Always writes a SyncLog row regardless of outcome.
     */
    public function syncFeed(Feed $feed, string $triggeredBy = 'scheduler'): ?JsonResponse
    {
        $feed->loadMissing('workspace');
        $userId = $feed->workspace?->owner_id;
        $startedAt = microtime(true);
        $syncStart = now();

        $credential = $feed->socialCredential;

        try {
            $token = $credential?->getValidAccessToken();
        } catch (\Throwable $e) {
            // Transient failure (network blip, config issue) — restore active so next cycle retries
            if ($credential && $credential->status !== 'active') {
                $credential->update(['status' => 'active']);
            }
            $this->writeLog($feed, $userId, 'error', 0, 'Token refresh error: ' . $e->getMessage(), $startedAt, $triggeredBy);
            return null;
        }

        if ($credential && $token === null) {
            $credential->update(['status' => 'disconnected']);
            $this->writeLog($feed, $userId, 'disconnected', 0, 'Token expired or revoked.', $startedAt, $triggeredBy);
            return null;
        }

        if ($credential && $credential->status !== 'active') {
            $credential->update(['status' => 'active']);
        }

        try {
            $result = match ($feed->type) {
                'youtube'   => $this->youtube->sync($feed),
                'rss'       => $this->rss->sync($feed),
                'facebook'  => $this->facebook->sync($feed),
                'instagram' => $this->instagram->sync($feed),
                'twitter'   => $this->twitter->sync($feed),
                'tiktok'    => $this->tiktok->sync($feed),
                'threads'   => $this->threads->sync($feed),
                default     => null,
            };
        } catch (\Throwable $e) {
            $this->writeLog($feed, $userId, 'error', 0, $e->getMessage(), $startedAt, $triggeredBy);
            return null;
        }

        if ($result === null) {
            $this->writeLog($feed, $userId, 'skipped', 0, null, $startedAt, $triggeredBy);
            return null;
        }

        $newPosts = Post::where('feed_id', $feed->id)
            ->where('created_at', '>=', $syncStart)
            ->count();

        $this->writeLog($feed, $userId, 'success', $newPosts, null, $startedAt, $triggeredBy);

        $workspace = $feed->workspace ?? $feed->load('workspace')->workspace;
        if ($workspace) {
            app(DuplicateDetectionService::class)->detectForWorkspace($workspace);
        }

        return $result;
    }

    private function writeLog(
        Feed $feed,
        ?int $userId,
        string $status,
        int $postsSynced,
        ?string $errorMessage,
        float $startedAt,
        string $triggeredBy,
    ): void {
        SyncLog::create([
            'feed_id'       => $feed->id,
            'user_id'       => $userId,
            'provider'      => $feed->type,
            'feed_name'     => $feed->name ?? $feed->type,
            'status'        => $status,
            'posts_synced'  => $postsSynced,
            'error_message' => $errorMessage,
            'duration_ms'   => (int) ((microtime(true) - $startedAt) * 1000),
            'triggered_by'  => $triggeredBy,
        ]);

        if (in_array($triggeredBy, ['queue', 'scheduler'], true)) {
            $feedName = $feed->name ?? $feed->type;
            match ($status) {
                'success'      => ActivityLogger::logForUserId(
                    $userId,
                    'feed.auto_synced',
                    "Auto-synced {$feed->type} feed \"{$feedName}\" ({$postsSynced} new post(s))",
                    'feed', $feed->id, $feedName,
                ),
                'error'        => ActivityLogger::logForUserId(
                    $userId,
                    'feed.sync_error',
                    "Auto-sync error for {$feed->type} feed \"{$feedName}\": {$errorMessage}",
                    'feed', $feed->id, $feedName,
                ),
                'disconnected' => ActivityLogger::logForUserId(
                    $userId,
                    'feed.sync_disconnected',
                    "Auto-sync: credential expired or revoked for {$feed->type} feed \"{$feedName}\"",
                    'feed', $feed->id, $feedName,
                ),
                default        => null,
            };
        }
    }
}
