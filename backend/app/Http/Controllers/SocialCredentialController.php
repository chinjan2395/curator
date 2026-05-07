<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabelSocialCredentialRequest;
use App\Http\Requests\StoreSocialCredentialRequest;
use App\Http\Requests\UpdateSocialCredentialRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\SocialCredentialResource;
use App\Models\SocialCredential;
use App\Repositories\Contracts\SocialCredentialRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialCredentialController extends Controller
{
    public function __construct(
        private readonly SocialCredentialRepositoryInterface $credentialRepository,
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

    public function destroy(Request $request, SocialCredential $socialCredential): JsonResponse
    {
        $this->authorizeOwner($request, $socialCredential);

        $this->credentialRepository->delete($socialCredential);

        return ApiResponse::noContent();
    }

    private function authorizeOwner(Request $request, SocialCredential $socialCredential): void
    {
        if ($socialCredential->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }
}
