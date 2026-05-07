<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Workspace;
use App\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedPublishController extends Controller
{
    public function __construct(private readonly PublishService $publishService) {}

    public function publish(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        return ApiResponse::success($this->publishService->publish($workspace), 'Publish complete.');
    }

    public function publishCode(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $workspace  = $this->publishService->ensurePublicKey($workspace);
        $base       = rtrim((string) config('app.url', ''), '/');
        $publicKey  = $workspace->public_key;
        $v          = (string) ($workspace->updated_at?->getTimestamp() ?? time());
        $cssUrl     = $base . '/api/embed/' . $publicKey . '.css?v=' . $v;
        $jsUrl      = $base . '/api/embed/' . $publicKey . '.js?v=' . $v;

        return ApiResponse::success([
            'public_key'       => $publicKey,
            'public_posts_url' => $base . '/api/public/feeds/' . $publicKey . '/posts',
            'embed_js_url'     => $jsUrl,
            'embed_css_url'    => $cssUrl,
            'embed_html'       => '<div data-curator-feed="' . $publicKey . '"></div>' . "\n"
                . '<link rel="stylesheet" href="' . $cssUrl . '" />' . "\n"
                . '<script src="' . $jsUrl . '"></script>',
        ]);
    }

    public function stats(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        return ApiResponse::success($this->publishService->getStats($workspace));
    }

    public function updateSettings(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $patch = $request->input('publish_settings');
        if (! is_array($patch)) {
            return ApiResponse::error('publish_settings must be an object');
        }

        $normalized = $this->publishService->updateSettings($workspace, $patch);

        return ApiResponse::success(['publish_settings' => $normalized], 'Settings updated.');
    }

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }
}
