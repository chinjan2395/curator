<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * A user's own API key (BYOK) for one AI image provider.
 *
 * Follows the same encryption idiom as SocialCredential / GoogleDriveConnection:
 * the secret lives only in the `*_encrypted` column, is never mass-assignable,
 * and is hidden from serialization so it cannot leak through an API resource.
 */
class AiProviderCredential extends Model
{
    protected $fillable = [
        'user_id',
        'kind',
        'provider',
        'api_key',
    ];

    protected $hidden = [
        'api_key',
        'api_key_encrypted',
    ];

    public function setApiKeyAttribute(?string $value): void
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            $this->attributes['api_key_encrypted'] = null;
            $this->attributes['key_last_four'] = null;

            return;
        }

        $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
        $this->attributes['key_last_four'] = substr($value, -4);
    }

    public function getApiKeyAttribute(): ?string
    {
        if (empty($this->attributes['api_key_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $this->attributes['api_key_encrypted']);
        } catch (\Throwable) {
            // A rotated APP_KEY leaves undecryptable rows; treat them as "no key"
            // so resolution falls through to the platform key instead of throwing.
            return null;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
