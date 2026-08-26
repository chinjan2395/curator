<?php

namespace App\Services\AI\Text;

use App\Models\AiProviderCredential;
use App\Models\User;
use App\Support\AiTextProviders;

/**
 * Decides which API key a text/caption generation runs with.
 *
 * Order: the user's own key (BYOK) wins, then the platform env key, otherwise
 * the provider is unusable and we say so instead of failing at the HTTP call.
 * Providers without a BYOK concept (Ollama) never need a key and are always
 * resolvable.
 */
class TextKeyResolver
{
    public function resolve(string $provider, ?User $user): ResolvedTextKey
    {
        $provider = strtolower(trim($provider));

        if (! AiTextProviders::exists($provider)) {
            throw AiProviderNotConfiguredException::for($provider);
        }

        if ($provider === AiTextProviders::STUB) {
            return ResolvedTextKey::none($provider);
        }

        if (! (AiTextProviders::find($provider)['byok'] ?? false)) {
            return ResolvedTextKey::none($provider);
        }

        $userKey = $this->userKey($provider, $user);
        if ($userKey !== null) {
            return ResolvedTextKey::byok($provider, $userKey);
        }

        $platformKey = AiTextProviders::platformKey($provider);
        if ($platformKey !== null) {
            return ResolvedTextKey::platform($provider, $platformKey);
        }

        throw AiProviderNotConfiguredException::for($provider);
    }

    /**
     * Whether the user could generate with this provider right now, without
     * resolving (and therefore decrypting) the key.
     */
    public function isAvailable(string $provider, ?User $user): bool
    {
        if ($provider === AiTextProviders::STUB) {
            return true;
        }

        if (! (AiTextProviders::find($provider)['byok'] ?? false)) {
            return true;
        }

        return $this->hasUserKey($provider, $user) || AiTextProviders::platformConfigured($provider);
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
            ->where('kind', 'text')
            ->where('provider', $provider)
            ->first();
    }

    private function userKey(string $provider, ?User $user): ?string
    {
        $key = $this->credential($provider, $user)?->api_key;

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }
}
