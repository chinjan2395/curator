<?php

namespace App\Services;

use App\DTOs\AuthData;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\EmailVerification;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function attemptLogin(string $email, string $password): ?array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            return null;
        }

        /** @var User $user */
        $user = EmailVerification::ensureVerified(Auth::user());

        if ($user->isDeactivated()) {
            Auth::logout();

            return ['deactivated' => true];
        }

        $user->update(['last_login_at' => now()]);
        ActivityLogger::log($user, 'auth.login', 'Logged in');

        return [
            'user'  => $user,
            'token' => $user->createToken('auth')->plainTextToken,
        ];
    }

    public function registerUser(AuthData $dto): array
    {
        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'is_onboarded' => false,
        ]);
        if (EmailVerification::required()) {
            $user->sendEmailVerificationNotification();
        } else {
            $user = EmailVerification::ensureVerified($user);
        }
        $token = $user->createToken('auth')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
