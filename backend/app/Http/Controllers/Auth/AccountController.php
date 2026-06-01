<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\SocialCredential;
use App\Models\Workspace;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $export = [
            'exported_at' => now()->toIso8601String(),
            'user' => $user->only([
                'id', 'name', 'email', 'role', 'avatar_url', 'industry',
                'target_audience', 'brand_voice', 'created_at',
            ]),
            'workspaces' => $user->workspaces()->with('feeds')->get()->toArray(),
            'social_credentials' => $user->socialCredentials()
                ->get(['id', 'provider', 'account_id', 'account_label', 'status', 'created_at'])
                ->toArray(),
            'campaigns' => Campaign::where('user_id', $user->id)->get()->toArray(),
            'content_packages' => ContentPackage::where('user_id', $user->id)->get()->toArray(),
        ];

        ActivityLogger::log($user, 'account.export', 'Exported account data');

        return ApiResponse::success($export, 'Account data export ready.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['required', 'accepted'],
        ]);

        $user = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($request->string('password')->value(), $user->password)) {
            return ApiResponse::error('Invalid password.', null, 422);
        }

        DB::transaction(function () use ($user): void {
            ActivityLog::where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        return ApiResponse::success(null, 'Account deleted.');
    }
}
