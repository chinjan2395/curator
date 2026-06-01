<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Models\TrendSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = TrendSnapshot::query()
            ->orderByDesc('captured_at')
            ->paginate((int) $request->query('per_page', 20));

        return ApiResponse::success($items);
    }
}
