<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function calendar(Request $request): JsonResponse
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->utc()
            : now()->utc()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->utc()
            : now()->utc()->endOfMonth();

        $posts = ScheduledPost::where('user_id', $request->user()->id)
            ->whereBetween('scheduled_at', [$from, $to])
            ->with(['socialCredential:id,provider,account_label', 'contentPackage:id,caption,platform'])
            ->orderBy('scheduled_at')
            ->get();

        return ApiResponse::success($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'social_credential_id' => ['required', 'exists:social_credentials,id'],
            'content_package_id' => ['nullable', 'exists:content_packages,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $credential = SocialCredential::findOrFail($validated['social_credential_id']);
        abort_if($credential->user_id !== $request->user()->id, 403);

        $post = ScheduledPost::create([
            'user_id' => $request->user()->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $validated['content_package_id'] ?? null,
            'scheduled_at' => Carbon::parse($validated['scheduled_at'])->utc(),
            'status' => 'scheduled',
        ]);

        return ApiResponse::success($post, 'Post scheduled.', 201);
    }

    public function cancel(Request $request, ScheduledPost $scheduledPost): JsonResponse
    {
        abort_if($scheduledPost->user_id !== $request->user()->id, 403);
        $scheduledPost->update(['status' => 'cancelled']);

        return ApiResponse::success($scheduledPost->fresh(), 'Schedule cancelled.');
    }

    public function queue(Request $request): JsonResponse
    {
        $posts = ScheduledPost::where('user_id', $request->user()->id)
            ->whereIn('status', ['scheduled', 'failed', 'published'])
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->get();

        return ApiResponse::success($posts);
    }
}
