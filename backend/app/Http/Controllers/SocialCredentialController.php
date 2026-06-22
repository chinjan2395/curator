<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabelSocialCredentialRequest;
use App\Http\Requests\StoreSocialCredentialRequest;
use App\Http\Requests\UpdateSocialCredentialRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\SocialCredentialResource;
use App\Jobs\SyncFeedJob;
use App\Models\Feed;
use App\Models\SocialCredential;
use App\Repositories\Contracts\SocialCredentialRepositoryInterface;
use App\Services\FeedSyncService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialCredentialController extends Controller
{
    public function __construct(
        private readonly SocialCredentialRepositoryInterface $credentialRepository,
        private readonly FeedSyncService $syncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            SocialCredentialResource::collection($this->credentialRepository->allForUser($request->user()))
        );
    }

    public function store(StoreSocialCredentialRequest $request): JsonResponse
    {
        $credential = $this->credentialRepository->create($request->user()->id, $request->validated());

        return ApiResponse::success(new SocialCredentialResource($credential), 'Credential saved.', 201);
    }

    public function show(Request $request, SocialCredential $socialCredential): JsonResponse
    {
        $this->authorizeOwner($request, $socialCredential);

        return ApiResponse::success(new SocialCredentialResource($socialCredential));
    }

    public function update(UpdateSocialCredentialRequest $request, SocialCredential $socialCredential): JsonResponse
    {
        $this->authorizeOwner($request, $socialCredential);

        $credential = $this->credentialRepository->update($socialCredential, $request->validated());

        return ApiResponse::success(new SocialCredentialResource($credential), 'Credential updated.');
    }

    public function label(LabelSocialCredentialRequest $request, SocialCredential $socialCredential): JsonResponse
    {
        $this->authorizeOwner($request, $socialCredential);

        $credential = $this->credentialRepository->update($socialCredential, ['account_label' => $request->validated('account_label')]);

        return ApiResponse::success(new SocialCredentialResource($credential), 'Label updated.');
    }

    public function sync(Request $request, SocialCredential $socialCredential): JsonResponse
    {
        $this->authorizeOwner($request, $socialCredential);

        $feeds = Feed::where('social_credential_id', $socialCredential->id)
            ->with(['socialCredential', 'workspace'])
            ->get();

        if ($feeds->isEmpty()) {
            return ApiResponse::success([
                'synced' => 0,
                'total'  => 0,
                'status' => $socialCredential->status,
            ], 'No feeds linked to this account.');
        }

        $synced = 0;
        foreach ($feeds as $feed) {
            SyncFeedJob::dispatch($feed->id, 'user');
            $synced++;
        }

        $socialCredential->refresh();

        $label = $socialCredential->account_label ?? $socialCredential->provider;
        ActivityLogger::log(
            $request->user(),
            'credential.synced',
            "Queued sync for {$synced} feed(s) for {$socialCredential->provider} account \"{$label}\"",
            'credential', $socialCredential->id, $label,
        );

        return ApiResponse::success([
            'synced' => $synced,
            'total'  => $feeds->count(),
            'status' => $socialCredential->status,
            'queued' => true,
        ], $synced > 0 ? "Sync started for {$synced} feed(s)." : 'Sync complete.');
    }

    public function destroy(Request $request, SocialCredential $socialCredential): JsonResponse
    {
        $this->authorizeOwner($request, $socialCredential);

        $label    = $socialCredential->account_label ?? $socialCredential->provider;
        $provider = $socialCredential->provider;

        $this->credentialRepository->delete($socialCredential);

        ActivityLogger::log(
            $request->user(),
            'credential.deleted',
            "Deleted {$provider} credential \"{$label}\"",
        );

        return ApiResponse::noContent();
    }

    private function authorizeOwner(Request $request, SocialCredential $socialCredential): void
    {
        if ($socialCredential->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }
}
