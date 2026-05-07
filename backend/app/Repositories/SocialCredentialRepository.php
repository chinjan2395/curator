<?php

namespace App\Repositories;

use App\Models\SocialCredential;
use App\Models\User;
use App\Repositories\Contracts\SocialCredentialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SocialCredentialRepository implements SocialCredentialRepositoryInterface
{
    public function allForUser(User $user): Collection
    {
        return SocialCredential::query()
            ->where('user_id', $user->id)
            ->orderBy('provider')
            ->get();
    }

    public function create(int $userId, array $data): SocialCredential
    {
        return SocialCredential::create(array_merge($data, ['user_id' => $userId]));
    }

    public function update(SocialCredential $credential, array $data): SocialCredential
    {
        $credential->update($data);

        return $credential->fresh();
    }

    public function delete(SocialCredential $credential): void
    {
        $credential->delete();
    }
}
