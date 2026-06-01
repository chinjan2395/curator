<?php

namespace App\Support;

use App\Models\User;

final class EmailVerification
{
    public static function required(): bool
    {
        return (bool) config('auth.require_email_verification');
    }

    /**
     * When verification is disabled (e.g. mail not configured), mark the user verified.
     */
    public static function ensureVerified(User $user): User
    {
        if (self::required() || $user->hasVerifiedEmail()) {
            return $user;
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->fresh();
    }
}
