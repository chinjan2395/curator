<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Jobs\GenerateCampaignContentJob;
use App\Models\BrandKit;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::where('user_id', $request->user()->id)
            ->with(['brandKit:id,name,colors,logo_url', 'template:id,name'])
            ->withCount('contentPackages')
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_info' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'array'],
            'tone' => ['nullable', 'string', 'max:50'],
            'goals' => ['nullable', 'array'],
            'platforms' => ['nullable', 'array'],
            'brand_kit_id' => ['nullable', 'integer', 'exists:brand_kits,id'],
            'template_id' => ['nullable', 'integer', 'exists:content_templates,id'],
        ]);

        if (isset($validated['brand_kit_id'])) {
            abort_unless(BrandKit::where('id', $validated['brand_kit_id'])->where('user_id', $userId)->exists(), 403);
        }

        $campaign = Campaign::create([
            ...$validated,
            'user_id' => $userId,
            'status' => 'draft',
        ]);

        return ApiResponse::success($campaign->load(['brandKit:id,name,colors,logo_url', 'template:id,name']), 'Campaign created.', 201);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        return ApiResponse::success(
            $campaign->load(['contentPackages', 'brandKit:id,name,colors,logo_url', 'template:id,name'])
        );
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'product_info' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'array'],
            'tone' => ['nullable', 'string', 'max:50'],
            'goals' => ['nullable', 'array'],
            'platforms' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'max:50'],
            'brand_kit_id' => ['nullable', 'integer', 'exists:brand_kits,id'],
            'template_id' => ['nullable', 'integer', 'exists:content_templates,id'],
        ]);

        if (isset($validated['brand_kit_id'])) {
            abort_unless(BrandKit::where('id', $validated['brand_kit_id'])->where('user_id', $userId)->exists(), 403);
        }

        $campaign->update($validated);

        return ApiResponse::success(
            $campaign->fresh()->load(['brandKit:id,name,colors,logo_url', 'template:id,name']),
            'Campaign updated.'
        );
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);
        $campaign->delete();

        return ApiResponse::success(null, 'Campaign deleted.');
    }

    public function generate(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        GenerateCampaignContentJob::dispatch($campaign->id, (int) $request->user()->id);

        return ApiResponse::success(
            ['campaign_id' => $campaign->id, 'queued' => true],
            'Content generation started.',
            202,
        );
    }

    private function authorizeCampaign(Request $request, Campaign $campaign): void
    {
        abort_if($campaign->user_id !== $request->user()->id, 403);
    }
}
