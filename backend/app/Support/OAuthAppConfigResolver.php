<?php

namespace App\Support;

use App\Models\OAuthAppConfig;

class OAuthAppConfigResolver
{
    public static function resolveForUser(int $userId, string $provider): ?OAuthAppConfig
    {
        $override = OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_USER)
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->first();

        if ($override) {
            return $override;
        }

        return OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_SHARED)
            ->whereNull('user_id')
            ->where('provider', $provider)
            ->first();
    }
}

