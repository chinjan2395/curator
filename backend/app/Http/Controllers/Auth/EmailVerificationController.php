<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail($request->integer('id'));

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->input('hash'))) {
            return ApiResponse::error('Invalid verification link.', null, 403);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return ApiResponse::success(null, 'Email verified successfully.');
    }

    public function resend(Request $request): JsonResponse
    {
        if (! EmailVerification::required()) {
            return ApiResponse::success(null, 'Email verification is not enabled.');
        }

        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Verification link sent.');
    }
}
