<?php

namespace App\Repositories;

use App\Models\BrandKit;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\Feed;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Every query behind the setup readiness contract.
 *
 * Kept deliberately cheap: these run on each authenticated page load, so nothing
 * here may make an HTTP call. In particular we read the persisted
 * SocialCredential::$token_health rather than calling refreshTokenHealth(),
 * which performs live provider token refreshes.
 */
class SetupReadinessRepository
{
    /**
     * Health values that mean a connected account cannot actually be used.
     * Mirrors BROKEN_HEALTH in frontend/src/stores/credentials.js so both sides
     * agree on what "connected" means. 'unknown' (the column default) is not
     * broken — it only means we have not verified the credential yet.
     */
    private const BROKEN_HEALTH = ['needs_reauth', 'expired', 'disconnected', 'error'];

    public function hasConnectedCredential(int $userId): bool
    {
        return SocialCredential::query()
            ->where('user_id', $userId)
            ->where('status', '!=', 'disconnected')
            ->where(function ($query) {
                $query->whereNull('token_health')
                    ->orWhereNotIn('token_health', self::BROKEN_HEALTH);
            })
            ->exists();
    }

    public function hasAnyCredential(int $userId): bool
    {
        return SocialCredential::query()->where('user_id', $userId)->exists();
    }

    /** @return list<int> */
    public function workspaceIds(int $userId): array
    {
        return Workspace::query()->where('owner_id', $userId)->pluck('id')->all();
    }

    /** @param list<int> $workspaceIds @return list<int> */
    public function feedIds(array $workspaceIds): array
    {
        if ($workspaceIds === []) {
            return [];
        }

        return Feed::query()->whereIn('workspace_id', $workspaceIds)->pluck('id')->all();
    }

    /** @param list<int> $feedIds */
    public function hasSyncedPosts(array $feedIds): bool
    {
        return $feedIds !== [] && Post::query()->whereIn('feed_id', $feedIds)->exists();
    }

    /** A Master brand kit is one with no parent — see BrandKit::resolve(). */
    public function hasMasterBrandKit(int $userId): bool
    {
        return BrandKit::query()
            ->where('user_id', $userId)
            ->whereNull('parent_id')
            ->exists();
    }

    public function hasCampaigns(int $userId): bool
    {
        return Campaign::query()->where('user_id', $userId)->exists();
    }

    public function hasApprovedPackages(int $userId): bool
    {
        return ContentPackage::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->exists();
    }

    public function hasScheduledPosts(int $userId): bool
    {
        return ScheduledPost::query()->where('user_id', $userId)->exists();
    }

    /**
     * Everyone who can register an OAuth app — the only people who can clear a
     * platform-scope blocker.
     *
     * @return Collection<int, User>
     */
    public function admins(): Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])
            ->whereNull('deactivated_at')
            ->get();
    }
}
