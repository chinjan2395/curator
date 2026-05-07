<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function allForUser(User $user): Collection
    {
        return $user->workspaces()->orderBy('name')->get();
    }

    public function find(int $id): ?Workspace
    {
        return Workspace::find($id);
    }

    public function create(User $user, array $data): Workspace
    {
        return $user->workspaces()->create($data);
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        return $workspace->fresh();
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }
}
