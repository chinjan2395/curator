<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuratorFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaceIds = Workspace::where('owner_id', $request->user()->id)->pluck('id');
        $feedIds = DB::table('feeds')->whereIn('workspace_id', $workspaceIds)->pluck('id');

        $query = Post::query()
            ->whereIn('feed_id', $feedIds)
            ->with(['feed:id,name,type,workspace_id']);

        if ($platform = $request->query('platform')) {
            $query->whereHas('feed', fn ($q) => $q->where('type', $platform));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($contentType = $request->query('content_type')) {
            $query->where('content_type', $contentType);
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'most_liked' => $query->orderByDesc('likes'),
            'most_viewed' => $query->orderByDesc('views'),
            'most_shared' => $query->orderByDesc('shares'),
            default => $query->orderByDesc('posted_at'),
        };

        $posts = $query->paginate((int) $request->query('per_page', 24));

        return ApiResponse::success($posts);
    }
}
