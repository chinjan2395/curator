<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNavigationSettingsRequest;
use App\Http\Resources\ApiResponse;
use App\Services\NavigationSettingsService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class NavigationSettingsController extends Controller
{
    public function __construct(
        private readonly NavigationSettingsService $navigationSettings,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->navigationSettings->getEffectiveSettings());
    }

    public function update(UpdateNavigationSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->navigationSettings->update($request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($settings, 'Navigation settings updated.');
    }
}
