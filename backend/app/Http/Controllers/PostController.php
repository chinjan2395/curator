<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Http\Request;

class PostController extends Controller
{
    private function authorizeWorkspace(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function ensureFeedInWorkspace(Workspace $workspace, Feed $feed): void
    {
        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    private function ensurePostInFeed(Feed $feed, Post $post): void
    {
        if ($post->feed_id !== $feed->id) {
            abort(404);
        }
    }

    public function index(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        $status = $request->query('status');

        $query = $feed->posts()
            ->orderByDesc('pinned')
            ->orderByDesc('posted_at');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    public function update(Request $request, Workspace $workspace, Feed $feed, Post $post)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);
        $this->ensurePostInFeed($feed, $post);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        $post->update(array_filter([
            'status' => $validated['status'] ?? null,
            'pinned' => array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
        ], fn ($v) => $v !== null));

        return response()->json($post);
    }

    public function destroy(Request $request, Workspace $workspace, Feed $feed, Post $post)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);
        $this->ensurePostInFeed($feed, $post);

        $post->delete();

        return response()->json(null, 204);
    }
}

