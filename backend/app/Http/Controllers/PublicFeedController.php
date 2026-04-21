<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Workspace;
use App\Support\PublishSettings;
use Illuminate\Http\Request;

class PublicFeedController extends Controller
{
    public function posts(Request $request, string $publicKey)
    {
        $workspace = Workspace::query()->where('public_key', $publicKey)->firstOrFail();

        $limit = (int) $request->query('limit', 25);
        $limit = max(1, min($limit, 100));
        $offset = (int) $request->query('offset', 0);
        $offset = max(0, $offset);

        $workspaceFeedIds = $workspace->feeds()->select('id');

        $baseQuery = Post::query()
            ->whereIn('feed_id', $workspaceFeedIds)
            ->whereNotNull('published_at')
            ->orderByDesc('pinned')
            ->orderByDesc('posted_at');

        $totalMatching = (clone $baseQuery)->count();

        $posts = (clone $baseQuery)
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'title', 'content', 'thumbnail_url', 'video_url', 'posted_at', 'external_id', 'pinned']);

        return response()->json([
            'feed' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => 'workspace',
                'public_key' => $workspace->public_key,
                'settings' => PublishSettings::merge($workspace->publish_settings),
            ],
            'count' => $posts->count(),
            'posts' => $posts,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $totalMatching,
                'has_more' => ($offset + $posts->count()) < $totalMatching,
            ],
        ]);
    }
}

