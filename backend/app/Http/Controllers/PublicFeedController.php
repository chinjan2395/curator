<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Support\PublishSettings;
use Illuminate\Http\Request;

class PublicFeedController extends Controller
{
    public function posts(Request $request, string $publicKey)
    {
        $feed = Feed::query()->where('public_key', $publicKey)->firstOrFail();

        $limit = (int) $request->query('limit', 25);
        $limit = max(1, min($limit, 100));
        $offset = (int) $request->query('offset', 0);
        $offset = max(0, $offset);

        $baseQuery = $feed->posts()
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
                'id' => $feed->id,
                'name' => $feed->name,
                'type' => $feed->type,
                'public_key' => $feed->public_key,
                'settings' => PublishSettings::merge($feed->publish_settings),
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

