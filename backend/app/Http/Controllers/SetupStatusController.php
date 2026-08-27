<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\User;
use App\Services\SetupReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetupStatusController extends Controller
{
    public function __construct(
        private readonly SetupReadinessService $readiness,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->readiness->forUser($user));
    }

    /**
     * Let a user who cannot clear a platform-scope blocker ask the people who can.
     */
    public function notifyAdmin(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->readiness->notifyAdmins($user);

        return ApiResponse::success(
            $result,
            $result['throttled']
                ? 'Your admins have already been notified today.'
                : 'Your admins have been notified.',
        );
    }
}
