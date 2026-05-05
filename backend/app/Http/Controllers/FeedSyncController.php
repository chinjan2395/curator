<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\Workspace;
use App\Services\FeedSyncService;
use App\Support\ActivityLogger;
use App\Sync\FacebookSyncer;
use App\Sync\InstagramSyncer;
use App\Sync\RssSyncer;
use App\Sync\ThreadsSyncer;
use App\Sync\TikTokSyncer;
use App\Sync\TwitterSyncer;
use App\Sync\YouTubeSyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedSyncController extends Controller
{
    public function __construct(
        private YouTubeSyncer $youtube,
        private FacebookSyncer $facebook,
        private InstagramSyncer $instagram,
        private TwitterSyncer $twitter,
        private TikTokSyncer $tiktok,
        private ThreadsSyncer $threads,
        private RssSyncer $rss,
        private FeedSyncService $syncService,
    ) {}

    // -------------------------------------------------------------------------
    // Account / channel discovery
    // -------------------------------------------------------------------------

    public function youtubeChannels(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'youtube');
        if (! $credential) {
            return response()->json(['message' => 'YouTube credential not found for this user.'], 404);
        }

        $result = $this->youtube->channels($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function twitterAccount(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'twitter');
        if (! $credential) {
            return response()->json(['message' => 'Twitter / X credential not found for this user.'], 404);
        }

        $result = $this->twitter->account($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function tiktokAccount(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'tiktok');
        if (! $credential) {
            return response()->json(['message' => 'TikTok credential not found for this user.'], 404);
        }

        $result = $this->tiktok->account($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function facebookPages(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'facebook');
        if (! $credential) {
            return response()->json(['message' => 'Facebook credential not found for this user.'], 404);
        }

        $result = $this->facebook->pages($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function threadsAccount(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'threads');
        if (! $credential) {
            return response()->json(['message' => 'Threads credential not found for this user.'], 404);
        }

        $result = $this->threads->account($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function instagramAccounts(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'instagram');
        if (! $credential) {
            return response()->json(['message' => 'Instagram credential not found for this user.'], 404);
        }

        $result = $this->instagram->accounts($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    // -------------------------------------------------------------------------
    // Connection tests
    // -------------------------------------------------------------------------

    public function testYouTube(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
            'youtube_channel_id' => ['required', 'string', 'max:255'],
        ]);

        $credential = $this->credentialForId($request, $validated['social_credential_id']);
        if (! $credential) {
            return response()->json(['message' => 'Credential not found for this user.'], 404);
        }

        $result = $this->youtube->test($credential, $validated['youtube_channel_id']);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function testRss(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $validated = $request->validate(['source_url' => ['required', 'string', 'max:500']]);

        $result = $this->rss->test(trim($validated['source_url']));

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function testFacebook(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
            'facebook_page_id' => ['required', 'string', 'max:255'],
        ]);

        $credential = $this->credentialFor($request, 'facebook');
        if (! $credential) {
            return response()->json(['message' => 'Facebook credential not found for this user.'], 404);
        }

        $result = $this->facebook->test($credential, trim($validated['facebook_page_id']), $request->user()->id);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function testInstagram(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
            'facebook_page_id' => ['required', 'string', 'max:255'],
            'instagram_business_account_id' => ['required', 'string', 'max:255'],
        ]);

        $credential = $this->credentialFor($request, 'instagram');
        if (! $credential) {
            return response()->json(['message' => 'Instagram credential not found for this user.'], 404);
        }

        $result = $this->instagram->test(
            $credential,
            trim($validated['facebook_page_id']),
            trim($validated['instagram_business_account_id']),
            $request->user()->id,
        );

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function testTwitter(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'twitter');
        if (! $credential) {
            return response()->json(['message' => 'Twitter / X credential not found for this user.'], 404);
        }

        $result = $this->twitter->test($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function testThreads(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'threads');
        if (! $credential) {
            return response()->json(['message' => 'Threads credential not found for this user.'], 404);
        }

        $result = $this->threads->test($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    public function testTikTok(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $request->validate(['social_credential_id' => ['required', 'integer', 'exists:social_credentials,id']]);

        $credential = $this->credentialFor($request, 'tiktok');
        if (! $credential) {
            return response()->json(['message' => 'TikTok credential not found for this user.'], 404);
        }

        $result = $this->tiktok->test($credential);

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    // -------------------------------------------------------------------------
    // Sync
    // -------------------------------------------------------------------------

    public function sync(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        $credErr = $this->ensureFeedUsesOwnerCredential($workspace, $feed);
        if ($credErr) {
            return $credErr;
        }

        $feed->load('socialCredential');

        if (in_array($feed->type, ['youtube', 'rss', 'facebook', 'instagram', 'twitter', 'tiktok', 'threads'], true)) {
            $result = $this->syncService->syncFeed($feed, 'user');
            if ($result !== null) {
                ActivityLogger::log($request->user(), 'feed.synced', "Synced {$feed->type} feed \"{$feed->name}\"", 'feed', $feed->id, $feed->name);
            }

            return $result ?? response()->json(['message' => 'Credential expired or revoked. Reconnect in Credentials.'], 422);
        }

        return $this->syncStub($request, $feed);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    private function ensureFeedUsesOwnerCredential(Workspace $workspace, Feed $feed): ?JsonResponse
    {
        $cid = $feed->social_credential_id;
        if ($cid === null) {
            return null;
        }

        $cred = SocialCredential::query()->find($cid);
        if (! $cred || (int) $cred->user_id !== (int) $workspace->owner_id) {
            return response()->json([
                'message' => 'This feed references a credential that does not belong to the workspace owner. Re-save the feed with your own credential.',
            ], 422);
        }

        return null;
    }

    private function credentialFor(Request $request, string $provider): ?SocialCredential
    {
        $id = $request->input('social_credential_id');

        return SocialCredential::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->first();
    }

    private function credentialForId(Request $request, int $id): ?SocialCredential
    {
        return SocialCredential::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();
    }

    private function syncStub(Request $request, Feed $feed): JsonResponse
    {
        $count = max(1, min((int) $request->input('count', 8), 30));
        $now = now();
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            Post::create([
                'feed_id' => $feed->id,
                'title' => ucfirst($feed->type).' sample post',
                'content' => $this->sampleContent($feed->type),
                'thumbnail_url' => null,
                'video_url' => null,
                'posted_at' => $now->copy()->subMinutes($i * 37),
                'external_id' => $feed->type.'-'.Str::random(10),
                'status' => 'pending',
                'pinned' => false,
            ]);
            $created++;
        }

        $feed->update(['last_synced_at' => $now]);

        return response()->json(['message' => 'Sync complete (stub)', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
    }

    private function sampleContent(string $type): string
    {
        $examples = [
            'instagram' => ["New drop: behind the scenes from today's shoot. #bts #studio", 'Quick carousel from the event—crowd energy was unreal.'],
            'youtube' => ['New video is live: "How we built the social wall (v1)"', 'Short: 3 UX details that make dashboards feel premium.'],
            'rss' => ['Blog: UGC best practices for moderation and brand safety.', 'Release notes: Feed filters + pinning are now available.'],
            'twitter' => ['Shipping a new curation flow today. Small UI details matter.', 'Poll: Which sources do you want next—TikTok or LinkedIn?'],
            'tiktok' => ['Fast cut: product demo in 15 seconds. Thoughts?', 'Creator spotlight: top community clips of the week.'],
            'facebook' => ['Community update: new templates are available in the dashboard.', 'Customer story: how a brand curated 5k posts safely.'],
            'other' => ['Sample post generated for this feed.', 'Another sample post to help test curation.'],
        ];

        $key = array_key_exists($type, $examples) ? $type : 'other';

        return $examples[$key][array_rand($examples[$key])];
    }
}
