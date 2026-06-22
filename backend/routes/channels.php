<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('admin.{userId}', function (User $user, int $userId) {
    return $user->isAdmin() && (int) $user->id === $userId;
});

Broadcast::channel('workspace.{workspaceId}', function (User $user, int $workspaceId) {
    return Workspace::query()
        ->where('id', $workspaceId)
        ->where('owner_id', $user->id)
        ->exists();
});
