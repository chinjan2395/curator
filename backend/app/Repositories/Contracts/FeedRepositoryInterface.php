<?php

namespace App\Repositories\Contracts;

use App\Models\Feed;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

interface FeedRepositoryInterface
{
    public function allForWorkspace(Workspace $workspace): Collection;

    public function find(int $id): ?Feed;

    public function create(Workspace $workspace, array $data): Feed;

    public function update(Feed $feed, array $data): Feed;

    public function delete(Feed $feed): void;

    public function hasProviderCredential(int $credentialId, int $userId, string $provider): bool;
}
