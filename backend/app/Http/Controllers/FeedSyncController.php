<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sync\SocialCredentialOnlyRequest;
use App\Http\Requests\Sync\TestFacebookRequest;
use App\Http\Requests\Sync\TestInstagramRequest;
use App\Http\Requests\Sync\TestRssRequest;
use App\Http\Requests\Sync\TestYouTubeRequest;
use App\Http\Resources\ApiResponse;
use App\Models\Feed;
use App\Models\SocialCredential;
use App\Models\Workspace;
use App\Repositories\Contracts\SocialCredentialRepositoryInterface;
use App\Services\FeedSyncService;
use App\Support\ActivityLogger;
use App\Support\PostSyncUpsert;
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
        private readonly YouTubeSyncer $youtube,
        private readonly FacebookSyncer $facebook,
        private readonly InstagramSyncer $instagram,
        private readonly TwitterSyncer $twitter,
        private readonly TikTokSyncer $tiktok,
        private readonly ThreadsSyncer $threads,
        private readonly RssSyncer $rss,
        private readonly FeedSyncService $syncService,
        private readonly SocialCredentialRepositoryInterface $credentialRepository,
    ) {}

    // -------------------------------------------------------------------------
    // Account / channel discovery
    // -------------------------------------------------------------------------

    public function youtubeChannels(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'youtube');
        if (! $credential) {
            return ApiResponse::error('YouTube credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->youtube->channels($credential));
    }

    public function twitterAccount(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'twitter');
        if (! $credential) {
            return ApiResponse::error('Twitter / X credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->twitter->account($credential));
    }

    public function tiktokAccount(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'tiktok');
        if (! $credential) {
            return ApiResponse::error('TikTok credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->tiktok->account($credential));
    }

    public function facebookPages(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'facebook');
        if (! $credential) {
            return ApiResponse::error('Facebook credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->facebook->pages($credential));
    }

    public function threadsAccount(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'threads');
        if (! $credential) {
            return ApiResponse::error('Threads credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->threads->account($credential));
    }

    public function instagramAccounts(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'instagram');
        if (! $credential) {
            return ApiResponse::error('Instagram credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->instagram->accounts($credential));
    }

    // -------------------------------------------------------------------------
    // Connection tests
    // -------------------------------------------------------------------------

    public function testYouTube(TestYouTubeRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialById($request, (int) $request->validated('social_credential_id'));
        if (! $credential) {
            return ApiResponse::error('Credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->youtube->test($credential, $request->validated('youtube_channel_id')));
    }

    public function testRss(TestRssRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        return $this->wrapSyncerResult($this->rss->test(trim($request->validated('source_url'))));
    }

    public function testFacebook(TestFacebookRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'facebook');
        if (! $credential) {
            return ApiResponse::error('Facebook credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult(
            $this->facebook->test($credential, trim($request->validated('facebook_page_id')), $request->user()->id)
        );
    }

    public function testInstagram(TestInstagramRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'instagram');
        if (! $credential) {
            return ApiResponse::error('Instagram credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->instagram->test(
            $credential,
            trim($request->validated('facebook_page_id')),
            trim($request->validated('instagram_business_account_id')),
            $request->user()->id,
        ));
    }

    public function testTwitter(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'twitter');
        if (! $credential) {
            return ApiResponse::error('Twitter / X credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->twitter->test($credential));
    }

    public function testThreads(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'threads');
        if (! $credential) {
            return ApiResponse::error('Threads credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->threads->test($credential));
    }

    public function testTikTok(SocialCredentialOnlyRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $credential = $this->credentialForProvider($request, 'tiktok');
        if (! $credential) {
            return ApiResponse::error('TikTok credential not found for this user.', null, 404);
        }

        return $this->wrapSyncerResult($this->tiktok->test($credential));
    }

    // -------------------------------------------------------------------------
    // Sync
    // -------------------------------------------------------------------------

    public function sync(Request $request, Workspace $workspace, Feed $feed): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
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

            return $result ?? ApiResponse::error('Credential expired or revoked. Reconnect in Credentials.');
        }

        return $this->syncStub($request, $feed);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function authorizeOwner(Request $request, Workspace $workspace): void
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

        $cred = SocialCredential::find($cid);
        if (! $cred || (int) $cred->user_id !== (int) $workspace->owner_id) {
            return ApiResponse::error('This feed references a credential that does not belong to the workspace owner. Re-save the feed with your own credential.');
        }

        return null;
    }

    private function credentialForProvider(Request $request, string $provider): ?SocialCredential
    {
        $id = (int) $request->input('social_credential_id');

        return SocialCredential::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->first();
    }

    private function credentialById(Request $request, int $id): ?SocialCredential
    {
        return SocialCredential::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();
    }

    private function wrapSyncerResult(mixed $result): JsonResponse
    {
        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    private function syncStub(Request $request, Feed $feed): JsonResponse
    {
        $count   = max(1, min((int) $request->input('count', 8), 30));
        $now     = now();
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            PostSyncUpsert::apply($feed, $feed->type.'-'.Str::random(10), [
                'title' => ucfirst($feed->type).' sample post',
                'content' => $this->sampleContent($feed->type),
                'thumbnail_url' => null,
                'video_url' => null,
                'posted_at' => $now->copy()->subMinutes($i * 37),
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
            'youtube'   => ['New video is live: "How we built the social wall (v1)"', 'Short: 3 UX details that make dashboards feel premium.'],
            'rss'       => ['Blog: UGC best practices for moderation and brand safety.', 'Release notes: Feed filters + pinning are now available.'],
            'twitter'   => ['Shipping a new curation flow today. Small UI details matter.', 'Poll: Which sources do you want next—TikTok or LinkedIn?'],
            'tiktok'    => ['Fast cut: product demo in 15 seconds. Thoughts?', 'Creator spotlight: top community clips of the week.'],
            'facebook'  => ['Community update: new templates are available in the dashboard.', 'Customer story: how a brand curated 5k posts safely.'],
            'other'     => ['Sample post generated for this feed.', 'Another sample post to help test curation.'],
        ];

        $key = array_key_exists($type, $examples) ? $type : 'other';

        return $examples[$key][array_rand($examples[$key])];
    }
}
