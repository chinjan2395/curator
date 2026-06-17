<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Campaign;
use App\Services\AutoPilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoPilotController extends Controller
{
    public function enable(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->update(['auto_pilot_enabled' => true]);

        return ApiResponse::success(
            $campaign->fresh()->load(['brandKit:id,name,colors,logo_url', 'template:id,name']),
            'Auto-pilot enabled.',
        );
    }

    public function disable(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $campaign->update(['auto_pilot_enabled' => false]);

        return ApiResponse::success(
            $campaign->fresh()->load(['brandKit:id,name,colors,logo_url', 'template:id,name']),
            'Auto-pilot disabled.',
        );
    }

    public function run(Request $request, Campaign $campaign, AutoPilotService $autoPilot): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $result = $autoPilot->run($campaign);

        return ApiResponse::success($result, 'Auto-pilot run completed.');
    }

    private function authorizeCampaign(Request $request, Campaign $campaign): void
    {
        abort_if($campaign->user_id !== $request->user()->id, 403);
    }
}
