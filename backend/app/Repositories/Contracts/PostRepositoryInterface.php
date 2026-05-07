<?php

namespace App\Repositories\Contracts;

use App\Models\Workspace;

interface PostRepositoryInterface
{
    public function publishApprovedForWorkspace(Workspace $workspace, \Illuminate\Support\Carbon $now): int;

    public function countApprovedForWorkspace(Workspace $workspace): int;

    public function countPublishedForWorkspace(Workspace $workspace): int;

    public function countPendingForWorkspace(Workspace $workspace): int;
}
