<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications): JsonResponse
    {
        return ApiResponse::success([
            'notifications' => $notifications->listForUser($request->user()),
            'unread_count' => $notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, int $id, NotificationService $notifications): JsonResponse
    {
        $notifications->markRead($request->user(), $id);

        return ApiResponse::success(null, 'Notification marked read.');
    }

    public function markAllRead(Request $request, NotificationService $notifications): JsonResponse
    {
        $notifications->markAllRead($request->user());

        return ApiResponse::success(null, 'All notifications marked read.');
    }

    public function preferences(Request $request): JsonResponse
    {
        $prefs = NotificationPreference::where('user_id', $request->user()->id)->get();

        return ApiResponse::success($prefs);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event_type' => ['required', 'string', 'max:80'],
            'preferences.*.in_app' => ['boolean'],
            'preferences.*.email' => ['boolean'],
            'preferences.*.push' => ['boolean'],
        ]);

        foreach ($validated['preferences'] as $pref) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'event_type' => $pref['event_type'],
                ],
                [
                    'in_app' => $pref['in_app'] ?? true,
                    'email' => $pref['email'] ?? false,
                    'push' => $pref['push'] ?? false,
                ],
            );
        }

        return ApiResponse::success(null, 'Preferences saved.');
    }
}
