<?php

namespace App\Support;

class OAuthProviderAliases
{
    /** @var array<string, list<string>> */
    private const OAUTH_LOOKUP_KEYS = [
        'google' => ['google', 'youtube'],
        'facebook' => ['facebook', 'instagram'],
        'twitter' => ['twitter'],
        'tiktok' => ['tiktok'],
        'threads' => ['threads'],
        'linkedin' => ['linkedin'],
    ];

    /** @var array<string, string> Social connect provider => OAuth app config provider key */
    private const SOCIAL_TO_OAUTH = [
        'youtube' => 'google',
        'google' => 'google',
        'facebook' => 'facebook',
        'instagram' => 'facebook',
        'twitter' => 'twitter',
        'tiktok' => 'tiktok',
        'threads' => 'threads',
        'linkedin' => 'linkedin',
    ];

    /** @return list<string> */
    public static function lookupKeys(string $oauthProvider): array
    {
        return self::OAUTH_LOOKUP_KEYS[$oauthProvider] ?? [$oauthProvider];
    }

    public static function oauthKeyForSocial(string $socialProvider): ?string
    {
        return self::SOCIAL_TO_OAUTH[$socialProvider] ?? null;
    }

    /** @return list<string> Canonical OAuth app config provider keys exposed in the API. */
    public static function canonicalOauthProviders(): array
    {
        return array_keys(self::OAUTH_LOOKUP_KEYS);
    }

    /** @return list<string> */
    public static function connectableSocialProviders(int $userId): array
    {
        $connectable = [];

        foreach (self::SOCIAL_TO_OAUTH as $socialProvider => $oauthProvider) {
            if (OAuthAppConfigResolver::resolveForUser($userId, $oauthProvider)) {
                $connectable[] = $socialProvider;
            }
        }

        return $connectable;
    }
}
