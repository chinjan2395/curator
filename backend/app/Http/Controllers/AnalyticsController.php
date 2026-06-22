<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Jobs\GenerateAnalyticsInsightsJob;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $data = $analytics->overview($request->user());
        $data['campaigns'] = $analytics->campaignPerformance($request->user());
        $data['time_series'] = $analytics->platformTimeSeries($request->user());

        return ApiResponse::success($data);
    }

    public function platform(Request $request, string $platform, AnalyticsService $analytics): JsonResponse
    {
        return ApiResponse::success($analytics->byPlatform($request->user(), $platform));
    }

    public function insights(Request $request): JsonResponse
    {
        GenerateAnalyticsInsightsJob::dispatch((int) $request->user()->id);

        return ApiResponse::success(
            ['queued' => true],
            'Insights generation started.',
            202,
        );
    }
}
