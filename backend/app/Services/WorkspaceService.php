<?php

namespace App\Services;

use App\DTOs\WorkspaceData;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceRepositoryInterface $workspaceRepository,
    ) {}

    public function listForUser(User $user): Collection
    {
        return $this->workspaceRepository->allForUser($user);
    }

    public function createWorkspace(User $user, WorkspaceData $data): Workspace
    {
        return $this->workspaceRepository->create($user, ['name' => $data->name]);
    }

    public function updateWorkspace(Workspace $workspace, WorkspaceData $data): Workspace
    {
        return $this->workspaceRepository->update($workspace, ['name' => $data->name]);
    }

    public function deleteWorkspace(Workspace $workspace): void
    {
        $this->workspaceRepository->delete($workspace);
    }
}
