<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\SocialCredential;
use App\Models\Workspace;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    private const CREDENTIAL_TYPES = ['youtube', 'facebook', 'twitter', 'tiktok'];

    private function authorizeWorkspace(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $feeds = $workspace->feeds()->orderBy('name')->get();

        return response()->json($feeds);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'social_credential_id' => ['nullable', 'integer', 'exists:social_credentials,id'],
            'youtube_channel_id' => ['nullable', 'string', 'max:255'],
            'facebook_page_id' => ['nullable', 'string', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:32'],
        ]);

        if ($validated['type'] === 'youtube') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a YouTube credential for this feed.',
                ], 422);
            }
            if (empty($validated['youtube_channel_id'])) {
                return response()->json([
                    'message' => 'Enter a YouTube channel ID or handle.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'youtube')) {
                return response()->json([
                    'message' => 'Select a valid YouTube credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'facebook') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a Facebook credential for this feed.',
                ], 422);
            }
            $pageId = trim((string) ($validated['facebook_page_id'] ?? ''));
            if ($pageId === '') {
                return response()->json([
                    'message' => 'Enter your Facebook Page ID.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'facebook')) {
                return response()->json([
                    'message' => 'Select a valid Facebook credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'twitter') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a Twitter / X credential for this feed.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'twitter')) {
                return response()->json([
                    'message' => 'Select a valid Twitter / X credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'tiktok') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a TikTok credential for this feed.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'tiktok')) {
                return response()->json([
                    'message' => 'Select a valid TikTok credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'rss') {
            $url = trim((string) ($validated['source_url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'message' => 'Enter a valid RSS URL.',
                ], 422);
            }
        }

        $feed = $workspace->feeds()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'source_url' => $validated['source_url'] ?? '',
            'social_credential_id' => in_array($validated['type'], self::CREDENTIAL_TYPES, true)
                ? ($validated['social_credential_id'] ?? null)
                : null,
            'youtube_channel_id' => $validated['type'] === 'youtube' ? ($validated['youtube_channel_id'] ?? null) : null,
            'facebook_page_id' => $validated['type'] === 'facebook' ? trim((string) ($validated['facebook_page_id'] ?? '')) : null,
            'twitter_username' => null,
        ]);

        return response()->json($feed, 201);
    }

    public function show(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);

        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }

        return response()->json($feed);
    }

    public function update(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);

        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }

        // Prevent editing a feed that already has approved posts.
        $hasAccepted = $feed->posts()->where('status', 'approved')->exists();
        if ($hasAccepted) {
            return response()->json([
                'message' => 'This feed has accepted posts and cannot be edited. Remove or unaccept those posts first.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'social_credential_id' => ['nullable', 'integer', 'exists:social_credentials,id'],
            'youtube_channel_id' => ['nullable', 'string', 'max:255'],
            'facebook_page_id' => ['nullable', 'string', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:32'],
        ]);

        if ($validated['type'] === 'youtube') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a YouTube credential for this feed.',
                ], 422);
            }
            if (empty($validated['youtube_channel_id'])) {
                return response()->json([
                    'message' => 'Enter a YouTube channel ID or handle.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'youtube')) {
                return response()->json([
                    'message' => 'Select a valid YouTube credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'facebook') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a Facebook credential for this feed.',
                ], 422);
            }
            $pageId = trim((string) ($validated['facebook_page_id'] ?? ''));
            if ($pageId === '') {
                return response()->json([
                    'message' => 'Enter your Facebook Page ID.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'facebook')) {
                return response()->json([
                    'message' => 'Select a valid Facebook credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'twitter') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a Twitter / X credential for this feed.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'twitter')) {
                return response()->json([
                    'message' => 'Select a valid Twitter / X credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'tiktok') {
            if (empty($validated['social_credential_id'])) {
                return response()->json([
                    'message' => 'Select a TikTok credential for this feed.',
                ], 422);
            }
            if (! $this->hasProviderCredential($request, (int) $validated['social_credential_id'], 'tiktok')) {
                return response()->json([
                    'message' => 'Select a valid TikTok credential.',
                ], 422);
            }
        }
        if ($validated['type'] === 'rss') {
            $url = trim((string) ($validated['source_url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'message' => 'Enter a valid RSS URL.',
                ], 422);
            }
        }

        $originalChannelId = $feed->youtube_channel_id;

        $updateData = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'source_url' => $validated['source_url'] ?? '',
            'social_credential_id' => in_array($validated['type'], self::CREDENTIAL_TYPES, true)
                ? ($validated['social_credential_id'] ?? null)
                : null,
            'youtube_channel_id' => $validated['type'] === 'youtube' ? ($validated['youtube_channel_id'] ?? null) : null,
            'facebook_page_id' => $validated['type'] === 'facebook' ? trim((string) ($validated['facebook_page_id'] ?? '')) : null,
            'twitter_username' => null,
        ];

        if ($validated['type'] === 'youtube' && ($validated['youtube_channel_id'] ?? null) !== $originalChannelId) {
            $updateData['youtube_uploads_playlist_id'] = null;
        }

        $feed->update($updateData);

        return response()->json($feed);
    }

    public function destroy(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);

        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }

        // Prevent deleting a feed that already has approved posts.
        $hasAccepted = $feed->posts()->where('status', 'approved')->exists();
        if ($hasAccepted) {
            return response()->json([
                'message' => 'This feed has accepted posts and cannot be deleted. Remove or unaccept those posts first.',
            ], 422);
        }

        $feed->delete();

        return response()->json(null, 204);
    }

    private function hasProviderCredential(Request $request, int $credentialId, string $provider): bool
    {
        return SocialCredential::query()
            ->where('id', $credentialId)
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->exists();
    }
}
