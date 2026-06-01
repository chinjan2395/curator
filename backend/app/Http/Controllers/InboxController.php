<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\InboxMessage;
use App\Services\InboxSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = InboxMessage::where('user_id', $request->user()->id)
            ->orderByDesc('received_at')
            ->limit(100)
            ->get();

        return ApiResponse::success($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'max:50'],
            'message_type' => ['required', 'string', 'max:50'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'post_url' => ['nullable', 'string', 'max:2048'],
            'social_credential_id' => ['nullable', 'exists:social_credentials,id'],
        ]);

        $message = InboxMessage::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'received_at' => now(),
        ]);

        return ApiResponse::success($message, 'Message recorded.', 201);
    }

    public function sync(Request $request, InboxSyncService $sync): JsonResponse
    {
        $count = $sync->ingestForUser($request->user());

        return ApiResponse::success(['ingested' => $count], "Inbox sync complete ({$count} new).");
    }
}
