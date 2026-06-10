<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmbedPostEventRequest;
use App\Models\EmbedPostEvent;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Http\Response;

class EmbedAnalyticsController extends Controller
{
    public function store(StoreEmbedPostEventRequest $request, string $publicKey, Post $post): Response
    {
        $workspace = Workspace::query()->where('public_key', $publicKey)->firstOrFail();

        $isTrackable = Post::query()
            ->whereKey($post->id)
            ->whereIn('feed_id', $workspace->feeds()->select('id'))
            ->where('status', 'approved')
            ->whereNotNull('published_at')
            ->exists();

        if (! $isTrackable) {
            abort(404);
        }

        $validated = $request->validated();

        EmbedPostEvent::query()->create([
            'workspace_id' => $workspace->id,
            'post_id' => $post->id,
            'event_type' => $validated['event_type'],
            'target_url' => $this->sanitizeUrl($validated['target_url'] ?? null),
            'page_url' => $this->sanitizeUrl($validated['page_url'] ?? null),
            'referrer' => $this->sanitizeUrl($validated['referrer'] ?? null),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            'ip_hash' => $this->hashIp((string) $request->ip()),
        ]);

        return response()->noContent();
    }

    private function sanitizeUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 2048) {
            return null;
        }

        return $value;
    }

    private function hashIp(string $ip): ?string
    {
        if ($ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.(string) config('app.key'));
    }
}
