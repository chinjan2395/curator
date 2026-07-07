<?php

namespace App\Services;

use App\Models\SocialCredential;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SocialCredentialHealthService
{
    /**
     * Live-verify a single credential by attempting to obtain a valid access token
     * (refreshing it when the provider supports it) and persisting the resulting
     * token_health. This reflects the REAL connection state, not just row existence.
     */
    public function verify(SocialCredential $credential): SocialCredential
    {
        try {
            $credential->refreshTokenHealth();
            $credential->save();
        } catch (\Throwable $e) {
            // Transient/provider errors (e.g. OAuth app misconfigured, network) —
            // surface as an error state instead of a misleading "valid".
            $credential->token_health = 'error';
            $credential->save();

            Log::warning('Social credential health check failed', [
                'credential_id' => $credential->id,
                'provider' => $credential->provider,
                'message' => $e->getMessage(),
            ]);
        }

        return $credential;
    }

    /**
     * Verify every credential in the given collection and return it with refreshed state.
     *
     * @param  Collection<int, SocialCredential>  $credentials
     * @return Collection<int, SocialCredential>
     */
    public function verifyMany(Collection $credentials): Collection
    {
        return $credentials->each(fn (SocialCredential $credential) => $this->verify($credential));
    }
}
