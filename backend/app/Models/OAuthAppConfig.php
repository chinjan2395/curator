<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class OAuthAppConfig extends Model
{
    public const SCOPE_USER = 'user';
    public const SCOPE_SHARED = 'shared';

    protected $table = 'oauth_app_configs';

    protected $fillable = [
        'user_id',
        'scope',
        'provider',
        'client_id',
        'redirect_uri',
        'client_secret_encrypted',
        // allow mass-assigning via mutator
        'client_secret',
    ];

    protected $hidden = [
        'client_secret_encrypted',
    ];

    protected $attributes = [
        'scope' => self::SCOPE_USER,
    ];

    public function setClientSecretAttribute(string $secret): void
    {
        $this->attributes['client_secret_encrypted'] = Crypt::encryptString($secret);
    }

    public function getClientSecretAttribute(): string
    {
        return Crypt::decryptString((string) $this->client_secret_encrypted);
    }
}

