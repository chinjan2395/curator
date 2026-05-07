<?php

namespace App\Repositories\Contracts;

use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface SocialCredentialRepositoryInterface
{
    public function allForUser(User $user): Collection;

    public function create(int $userId, array $data): SocialCredential;

    public function update(SocialCredential $credential, array $data): SocialCredential;

    public function delete(SocialCredential $credential): void;
}
