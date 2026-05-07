<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

interface WorkspaceRepositoryInterface
{
    public function allForUser(User $user): Collection;

    public function find(int $id): ?Workspace;

    public function create(User $user, array $data): Workspace;

    public function update(Workspace $workspace, array $data): Workspace;

    public function delete(Workspace $workspace): void;
}
