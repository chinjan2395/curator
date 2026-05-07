<?php

namespace App\Repositories;

use App\Models\Feed;
use App\Models\SocialCredential;
use App\Models\Workspace;
use App\Repositories\Contracts\FeedRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FeedRepository implements FeedRepositoryInterface
{
    public function allForWorkspace(Workspace $workspace): Collection
    {
        return $workspace->feeds()->orderBy('name')->get();
    }

    public function find(int $id): ?Feed
    {
        return Feed::find($id);
    }

    public function create(Workspace $workspace, array $data): Feed
    {
        return $workspace->feeds()->create($data);
    }

    public function update(Feed $feed, array $data): Feed
    {
        $feed->update($data);

        return $feed->fresh();
    }

    public function delete(Feed $feed): void
    {
        $feed->delete();
    }

    public function hasProviderCredential(int $credentialId, int $userId, string $provider): bool
    {
        return SocialCredential::query()
            ->where('id', $credentialId)
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->exists();
    }
}
