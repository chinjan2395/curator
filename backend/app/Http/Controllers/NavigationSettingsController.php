<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Services\NavigationSettingsService;
use Illuminate\Http\JsonResponse;

class NavigationSettingsController extends Controller
{
    public function __construct(
        private readonly NavigationSettingsService $navigationSettings,
    ) {}

    public function show(): JsonResponse
    {
        $settings = $this->navigationSettings->getEffectiveSettings();

        return ApiResponse::success([
            'menus' => $settings['menus'],
            'features' => $settings['features'],
        ]);
    }
}
