<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attemptLogin(
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        if ($result === null) {
            return ApiResponse::error('Invalid credentials');
        }

        if (isset($result['deactivated'])) {
            return ApiResponse::error('Your account has been deactivated. Please contact support.', null, 403);
        }

        return ApiResponse::success([
            'user'  => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Login successful.');
    }
}
