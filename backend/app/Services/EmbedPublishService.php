<?php

namespace App\Services;

use App\Models\EmbedPostEvent;
use App\Models\Workspace;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Support\PublishSettings;
use Illuminate\Support\Str;

/**
 * Publishes curated posts to the public website embed widget (not native social posting).
 */
class EmbedPublishService
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    public function publish(Workspace $workspace): array
    {
        if (! $workspace->public_key) {
            $workspace->public_key = Str::random(32);
        }

        $now = now();
        $count = $this->postRepository->publishApprovedForWorkspace($workspace, $now);

        $workspace->last_published_at = $now;
        $workspace->save();

        return [
            'message' => 'Embed publish complete',
            'published' => $count,
            'public_key' => $workspace->public_key,
            'last_published_at' => $workspace->last_published_at,
        ];
    }

    public function getStats(Workspace $workspace): array
    {
        return [
            'approved' => $this->postRepository->countApprovedForWorkspace($workspace),
            'published' => $this->postRepository->countPublishedForWorkspace($workspace),
            'pending' => $this->postRepository->countPendingForWorkspace($workspace),
            'embed_clicks' => (int) EmbedPostEvent::query()
                ->where('workspace_id', $workspace->id)
                ->where('event_type', EmbedPostEvent::TYPE_POST_CLICK)
                ->count(),
            'last_published_at' => $workspace->last_published_at,
            'public_key' => $workspace->public_key,
            'publish_settings' => PublishSettings::merge($workspace->publish_settings),
        ];
    }

    public function ensurePublicKey(Workspace $workspace): Workspace
    {
        if (! $workspace->public_key) {
            $workspace->public_key = Str::random(32);
            $workspace->save();
        }

        return $workspace->fresh();
    }

    public function updateSettings(Workspace $workspace, array $patch): array
    {
        $current = PublishSettings::merge($workspace->publish_settings);
        $normalized = PublishSettings::validateAndNormalize(array_replace_recursive($current, $patch));

        $workspace->publish_settings = $normalized;
        $workspace->save();

        return $normalized;
    }
}
