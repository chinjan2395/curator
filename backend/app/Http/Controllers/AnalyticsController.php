<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Services\AI\AiInsightsService;
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

    public function insights(Request $request, AnalyticsService $analytics, AiInsightsService $aiInsights): JsonResponse
    {
        $overview = $analytics->overview($request->user());
        $heuristic = $analytics->insights($request->user());
        $llm = $aiInsights->generateInsights($overview);

        return ApiResponse::success([
            'insights' => array_values(array_unique(array_merge($llm, $heuristic))),
        ]);
    }
}
