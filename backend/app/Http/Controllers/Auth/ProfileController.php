<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\UserResource;
use App\Support\EmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = EmailVerification::ensureVerified($request->user());

        return ApiResponse::success(new UserResource($user));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'target_audience' => ['sometimes', 'nullable', 'string'],
            'brand_voice' => ['sometimes', 'nullable', 'string'],
            'is_onboarded' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->fill($validated);
        $user->save();

        return ApiResponse::success(new UserResource($user->fresh()), 'Profile updated.');
    }
}
