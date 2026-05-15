<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogger
{
    public static function log(
        User $user,
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityName = null,
    ): void {
        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'entity_name' => $entityName,
        ]);
    }

    public static function logForUserId(
        ?int $userId,
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityName = null,
    ): void {
        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'entity_name' => $entityName,
        ]);
    }
}
