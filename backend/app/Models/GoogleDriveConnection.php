<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class GoogleDriveConnection extends Model
{
    protected $fillable = [
        'connected_by_user_id',
        'account_email',
        'account_label',
        'refresh_token',
        'access_token',
        'expires_at',
        'token_health',
        'last_error',
        'connected_at',
    ];

    protected $hidden = [
        'refresh_token',
        'access_token',
        'refresh_token_encrypted',
        'access_token_encrypted',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['refresh_token_encrypted'] = null;

            return;
        }

        $this->attributes['refresh_token_encrypted'] = Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute(): ?string
    {
        if (empty($this->attributes['refresh_token_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $this->attributes['refresh_token_encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['access_token_encrypted'] = null;

            return;
        }

        $this->attributes['access_token_encrypted'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(): ?string
    {
        if (empty($this->attributes['access_token_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $this->attributes['access_token_encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function current(): ?self
    {
        return static::query()->orderByDesc('id')->first();
    }

    public function markNeedsReauth(?string $error = null): void
    {
        $this->token_health = 'needs_reauth';
        $this->last_error = $error;
        $this->access_token = null;
        $this->expires_at = null;
        $this->save();
    }

    public function markValid(): void
    {
        $this->token_health = 'valid';
        $this->last_error = null;
        $this->save();
    }

    public function toStatusArray(): array
    {
        return [
            'connected' => true,
            'account_email' => $this->account_email,
            'account_label' => $this->account_label,
            'token_health' => $this->token_health,
            'last_error' => $this->last_error,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'source' => 'database',
        ];
    }
}
