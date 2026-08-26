<?php

namespace App\Services\AI\Image;

use App\Models\AiProviderCredential;
use App\Models\User;
use App\Support\AiImageProviders;

/**
 * Decides which API key an image generation runs with.
 *
 * Order: the user's own key (BYOK) wins, then the platform env key, otherwise
 * the provider is unusable and we say so instead of failing at the HTTP call.
 */
class ImageKeyResolver
{
    public function resolve(string $provider, ?User $user): ResolvedImageKey
    {
        $provider = strtolower(trim($provider));

        if (! AiImageProviders::exists($provider)) {
            throw AiProviderNotConfiguredException::for($provider);
        }

        if ($provider === AiImageProviders::STUB) {
            return ResolvedImageKey::none($provider);
        }

        $userKey = $this->userKey($provider, $user);
        if ($userKey !== null) {
            return ResolvedImageKey::byok($provider, $userKey);
        }

        $platformKey = AiImageProviders::platformKey($provider);
        if ($platformKey !== null) {
            return ResolvedImageKey::platform($provider, $platformKey);
        }

        throw AiProviderNotConfiguredException::for($provider);
    }

    /**
     * Whether the user could generate with this provider right now, without
     * resolving (and therefore decrypting) the key.
     */
    public function isAvailable(string $provider, ?User $user): bool
    {
        if ($provider === AiImageProviders::STUB) {
            return true;
        }

        return $this->hasUserKey($provider, $user) || AiImageProviders::platformConfigured($provider);
    }

    public function hasUserKey(string $provider, ?User $user): bool
    {
        return $this->credential($provider, $user) !== null;
    }

    public function credential(string $provider, ?User $user): ?AiProviderCredential
    {
        if ($user === null) {
            return null;
        }

        return AiProviderCredential::query()
            ->where('user_id', $user->id)
            ->where('kind', 'image')
            ->where('provider', $provider)
            ->first();
    }

    private function userKey(string $provider, ?User $user): ?string
    {
        $key = $this->credential($provider, $user)?->api_key;

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }
}
