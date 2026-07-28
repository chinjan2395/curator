<?php

namespace App\Http\Controllers;

use App\DTOs\WorkspaceData;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\SocialCredentialResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Repositories\Contracts\SocialCredentialRepositoryInterface;
use App\Services\WorkspaceService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly SocialCredentialRepositoryInterface $credentialRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspaces = $this->workspaceService->listForUser($request->user());

        return ApiResponse::success(WorkspaceResource::collection($workspaces));
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaceService->createWorkspace($request->user(), WorkspaceData::fromArray($request->validated()));

        ActivityLogger::log($request->user(), 'workspace.created', "Created workspace \"{$workspace->name}\"", 'workspace', $workspace->id, $workspace->name);

        return ApiResponse::success(new WorkspaceResource($workspace), 'Workspace created.', 201);
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        return ApiResponse::success(new WorkspaceResource($workspace->load('owner:id,name,email')));
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $workspace = $this->workspaceService->updateWorkspace($workspace, WorkspaceData::fromArray($request->validated()));

        ActivityLogger::log($request->user(), 'workspace.updated', "Updated workspace \"{$workspace->name}\"", 'workspace', $workspace->id, $workspace->name);

        return ApiResponse::success(new WorkspaceResource($workspace), 'Workspace updated.');
    }

    /**
     * The connected social accounts to use for feed setup in this workspace — always the
     * workspace *owner's* credentials, so a super admin acting on behalf of another user
     * (or that user themselves) sees the right accounts to pick from.
     */
    public function credentials(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $owner = $workspace->owner ?? $workspace->owner()->first();

        return ApiResponse::success(
            SocialCredentialResource::collection($this->credentialRepository->allForUser($owner))
        );
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        ActivityLogger::log($request->user(), 'workspace.deleted', "Deleted workspace \"{$workspace->name}\"", 'workspace', $workspace->id, $workspace->name);

        $this->workspaceService->deleteWorkspace($workspace);

        return ApiResponse::noContent();
    }

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        if (! $workspace->isAccessibleBy($request->user())) {
            abort(403, 'Unauthorized');
        }
    }
}
