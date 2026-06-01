<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Workspace;
use App\Support\FeedAccountDisplay;
use App\Support\PublishSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicFeedController extends Controller
{
    public function posts(Request $request, string $publicKey)
    {
        $cacheKey = 'public_feed:'.$publicKey.':'.md5($request->getQueryString() ?? '');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($request, $publicKey) {
            return $this->buildPostsResponse($request, $publicKey);
        });
    }

    private function buildPostsResponse(Request $request, string $publicKey)
    {
        $workspace = Workspace::query()->where('public_key', $publicKey)->firstOrFail();
        $settings = PublishSettings::merge($workspace->publish_settings);

        $limit = (int) $request->query('limit', 25);
        $limit = max(1, min($limit, 100));
        $offset = (int) $request->query('offset', 0);
        $offset = max(0, $offset);

        $workspaceFeedIds = $workspace->feeds()->select('id');

        $baseQuery = Post::query()
            ->whereIn('feed_id', $workspaceFeedIds)
            ->where('status', 'approved')
            ->whereNotNull('published_at');

        if (! empty($settings['widget']['platform_filters'])) {
            $platforms = $settings['widget']['platform_filters'];
            $baseQuery->whereHas('feed', fn ($q) => $q->whereIn('type', $platforms));
        }

        if (! empty($settings['widget']['content_type_filters'])) {
            $baseQuery->whereIn('content_type', $settings['widget']['content_type_filters']);
        }

        $baseQuery->orderByDesc('pinned')->orderByDesc('posted_at');

        $totalMatching = (clone $baseQuery)->count();

        $posts = (clone $baseQuery)
            ->with(['feed'])
            ->offset($offset)
            ->limit($limit)
            ->get([
                'id', 'feed_id', 'title', 'content', 'thumbnail_url', 'video_url', 'post_url',
                'posted_at', 'external_id', 'pinned', 'content_type',
                'likes', 'comments', 'shares', 'views',
            ]);

        $serialized = $posts->map(static function (Post $post): array {
            $feed = $post->feed;

            return [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'thumbnail_url' => $post->thumbnail_url,
                'video_url' => $post->video_url,
                'post_url' => $post->post_url ?? $post->video_url,
                'content_type' => $post->content_type,
                'likes' => (int) $post->likes,
                'comments' => (int) $post->comments,
                'shares' => (int) $post->shares,
                'views' => (int) $post->views,
                'posted_at' => $post->posted_at,
                'external_id' => $post->external_id,
                'pinned' => (bool) $post->pinned,
                'provider' => $feed?->type,
                'feed_name' => $feed?->name,
                'account_label' => FeedAccountDisplay::resolve($feed),
                'account_avatar_url' => $feed?->account_avatar_url
                    ? (string) $feed->account_avatar_url
                    : null,
            ];
        });

        return response()->json([
            'feed' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => 'workspace',
                'public_key' => $workspace->public_key,
                'settings' => PublishSettings::merge($workspace->publish_settings),
            ],
            'count' => $serialized->count(),
            'posts' => $serialized,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $totalMatching,
                'has_more' => ($offset + $posts->count()) < $totalMatching,
            ],
        ]);
    }
}

