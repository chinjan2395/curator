<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Campaign;
use App\Services\AI\AiContentService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::where('user_id', $request->user()->id)
            ->withCount('contentPackages')
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_info' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'array'],
            'tone' => ['nullable', 'string', 'max:50'],
            'goals' => ['nullable', 'array'],
            'platforms' => ['nullable', 'array'],
        ]);

        $campaign = Campaign::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'draft',
        ]);

        return ApiResponse::success($campaign, 'Campaign created.', 201);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        return ApiResponse::success($campaign->load('contentPackages'));
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_info' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'array'],
            'tone' => ['nullable', 'string', 'max:50'],
            'goals' => ['nullable', 'array'],
            'platforms' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        $campaign->update($validated);

        return ApiResponse::success($campaign->fresh(), 'Campaign updated.');
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);
        $campaign->delete();

        return ApiResponse::success(null, 'Campaign deleted.');
    }

    public function generate(Request $request, Campaign $campaign, AiContentService $ai, NotificationService $notifications): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);
        $packages = $ai->generateForCampaign($campaign);

        $notifications->notify(
            $request->user(),
            'campaign_generated',
            'Campaign content generated',
            "AI generated content for campaign \"{$campaign->name}\".",
            ['campaign_id' => $campaign->id],
        );

        return ApiResponse::success($packages, 'Content generated.');
    }

    private function authorizeCampaign(Request $request, Campaign $campaign): void
    {
        abort_if($campaign->user_id !== $request->user()->id, 403);
    }
}
