<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->where('status', 'pending')
            ->with(['feed.workspace'])
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 25));

        return ApiResponse::success($posts);
    }
}
