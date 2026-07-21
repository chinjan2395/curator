<?php

namespace App\Support;

use App\Models\OAuthAppConfig;

class OAuthAppConfigResolver
{
    public static function resolveForUser(int $userId, string $provider): ?OAuthAppConfig
    {
        foreach (OAuthProviderAliases::lookupKeys($provider) as $lookupProvider) {
            $override = OAuthAppConfig::query()
                ->where('scope', OAuthAppConfig::SCOPE_USER)
                ->where('user_id', $userId)
                ->where('provider', $lookupProvider)
                ->first();

            if ($override) {
                return $override;
            }
        }

        foreach (OAuthProviderAliases::lookupKeys($provider) as $lookupProvider) {
            $shared = OAuthAppConfig::query()
                ->where('scope', OAuthAppConfig::SCOPE_SHARED)
                ->whereNull('user_id')
                ->where('provider', $lookupProvider)
                ->first();

            if ($shared) {
                return $shared;
            }
        }

        return null;
    }
}

