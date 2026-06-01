<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'avatar_url',
        'industry',
        'target_audience',
        'brand_voice',
        'ai_prompt_overrides',
        'is_onboarded',
        'social_provider',
        'social_provider_id',
        'deactivated_at',
        'last_login_at',
        'sync_notifications_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'sync_notifications_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_onboarded' => 'boolean',
            'ai_prompt_overrides' => 'array',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERADMIN], true);
    }

    public function needsOnboarding(): bool
    {
        return ! $this->is_onboarded;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function workspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function socialCredentials()
    {
        return $this->hasMany(SocialCredential::class);
    }
}
