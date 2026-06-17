<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Services\Social\SocialPublisherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'content_package_id' => ['required', 'exists:content_packages,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $credential = SocialCredential::findOrFail($validated['social_credential_id']);
        abort_if($credential->user_id !== $request->user()->id, 403);

        if (! in_array($credential->provider, SocialPublisherService::NATIVE_PUBLISH_PROVIDERS, true)) {
            throw ValidationException::withMessages([
                'social_credential_id' => [
                    ucfirst($credential->provider).' does not support native scheduling. Use embed publishing or pick X, Facebook, Instagram, TikTok, Threads, or LinkedIn.',
                ],
            ]);
        }

        $package = ContentPackage::findOrFail($validated['content_package_id']);
        abort_if($package->user_id !== $request->user()->id, 403);

        if (trim((string) $package->caption) === '') {
            throw ValidationException::withMessages([
                'content_package_id' => ['Content package must have caption text before scheduling native publish.'],
            ]);
        }

        $post = ScheduledPost::create([
            'user_id' => $request->user()->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
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
            ->with(['socialCredential:id,provider,account_label', 'contentPackage:id,caption,platform'])
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->get();

        return ApiResponse::success($posts);
    }

    public function retry(Request $request, ScheduledPost $scheduledPost): JsonResponse
    {
        abort_if($scheduledPost->user_id !== $request->user()->id, 403);
        abort_if($scheduledPost->status !== 'failed', 422, 'Only failed posts can be retried.');

        $scheduledPost->update([
            'status' => 'scheduled',
            'retry_count' => 0,
            'scheduled_at' => now()->addMinute(),
            'error_message' => null,
        ]);

        return ApiResponse::success(
            $scheduledPost->fresh(['socialCredential', 'contentPackage']),
            'Post queued for retry. It will publish within a minute.'
        );
    }
}
