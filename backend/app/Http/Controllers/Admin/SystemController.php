<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Models\Campaign;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function overview(): JsonResponse
    {
        return ApiResponse::success([
            'users' => User::count(),
            'campaigns' => Campaign::count(),
            'active_credentials' => SocialCredential::where('status', 'active')->count(),
            'broken_credentials' => SocialCredential::whereIn('token_health', ['needs_reauth', 'expired', 'error'])->count(),
            'scheduled_posts' => ScheduledPost::where('status', 'scheduled')->count(),
            'failed_posts' => ScheduledPost::where('status', 'failed')->count(),
            'queue_size' => DB::table('jobs')->count(),
        ]);
    }

    public function integrationHealth(): JsonResponse
    {
        $byProvider = SocialCredential::query()
            ->selectRaw('provider, token_health, count(*) as total')
            ->groupBy('provider', 'token_health')
            ->get();

        return ApiResponse::success($byProvider);
    }
}
