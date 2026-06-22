<?php

namespace App\Services\Social;

use App\Models\ScheduledPost;
use App\Events\ScheduledPostStatusChanged;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use App\Services\Social\Publishers\PublisherInterface;
use App\Services\Social\Publishers\FacebookPublisher;
use App\Services\Social\Publishers\InstagramPublisher;
use App\Services\Social\Publishers\StubPublisher;
use App\Services\Social\Publishers\LinkedInPublisher;
use App\Services\Social\Publishers\ThreadsPublisher;
use App\Services\Social\Publishers\TikTokPublisher;
use App\Services\Social\Publishers\TwitterPublisher;

class SocialPublisherService
{
    /** @var list<string> */
    public const NATIVE_PUBLISH_PROVIDERS = [
        'twitter',
        'facebook',
        'instagram',
        'tiktok',
        'threads',
        'linkedin',
    ];

    /** @var array<string, PublisherInterface> */
    private array $publishers = [];

    public function __construct()
    {
        $this->publishers['stub'] = new StubPublisher;
        $this->publishers['twitter'] = new TwitterPublisher;
        $this->publishers['facebook'] = new FacebookPublisher;
        $this->publishers['instagram'] = new InstagramPublisher;
        $this->publishers['tiktok'] = new TikTokPublisher;
        $this->publishers['threads'] = new ThreadsPublisher;
        $this->publishers['linkedin'] = new LinkedInPublisher;
    }

    public function publish(ScheduledPost $scheduledPost, ?NotificationService $notifications = null): void
    {
        $notifications ??= app(NotificationService::class);

        // Scheduled-post queries often eager-load trimmed relations (id/provider only).
        // loadMissing() would skip a reload, leaving tokens null and falsely "expired".
        $scheduledPost->unsetRelation('socialCredential');
        $scheduledPost->unsetRelation('contentPackage');
        $scheduledPost->load(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        $provider = $credential?->provider ?? 'stub';
        $publisher = $this->publishers[$provider] ?? $this->publishers['stub'];

        try {
            $result = $publisher->publish($scheduledPost);
            $scheduledPost->update([
                'status' => 'published',
                'published_at' => now(),
                'platform_post_id' => $result['platform_post_id'] ?? null,
                'platform_post_url' => $result['platform_post_url'] ?? null,
                'error_message' => null,
            ]);

            $notifications->notify(
                $scheduledPost->user,
                'post_published',
                'Post published',
                "Your scheduled post was published to {$provider}.",
                ['scheduled_post_id' => $scheduledPost->id],
            );

            event(ScheduledPostStatusChanged::fromModel($scheduledPost->fresh()));
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($publisher instanceof StubPublisher && ! app()->environment('local')) {
                $message = 'Native publisher not configured for '.$provider.'. Set up platform write APIs or use embed publishing.';
            }

            $retry = $scheduledPost->retry_count + 1;
            $scheduledPost->update([
                'status' => $retry >= 3 ? 'failed' : 'scheduled',
                'error_message' => $message,
                'retry_count' => $retry,
                'scheduled_at' => $retry < 3 ? now()->addMinutes(15) : $scheduledPost->scheduled_at,
            ]);

            Log::warning('Scheduled post publish failed', [
                'scheduled_post_id' => $scheduledPost->id,
                'provider' => $provider,
                'retry_count' => $retry,
                'error' => $message,
            ]);

            $scheduledPost->loadMissing('user');
            $notifications->notify(
                $scheduledPost->user,
                'post_failed',
                'Post publish failed',
                $message,
                ['scheduled_post_id' => $scheduledPost->id],
            );

            event(ScheduledPostStatusChanged::fromModel($scheduledPost->fresh()));
        }
    }
}
