<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\BrandKit;
use App\Models\Workspace;
use App\Services\PublishService;
use App\Support\ActivityLogger;
use App\Support\BrandKitExpandedSettings;
use App\Support\PublicFeedCache;
use App\Support\PublishSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedPublishController extends Controller
{
    public function __construct(private readonly PublishService $publishService) {}

    public function publish(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $result = $this->publishService->publish($workspace);

        ActivityLogger::log(
            $request->user(),
            'workspace.published',
            "Published {$result['published']} post(s) for workspace \"{$workspace->name}\"",
            'workspace', $workspace->id, $workspace->name,
        );

        return ApiResponse::success($result, 'Publish complete.');
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

        ActivityLogger::log(
            $request->user(),
            'workspace.settings_updated',
            "Updated publish settings for workspace \"{$workspace->name}\"",
            'workspace', $workspace->id, $workspace->name,
        );

        return ApiResponse::success(['publish_settings' => $normalized], 'Settings updated.');
    }

    public function applyBrandKit(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $validated = $request->validate([
            'brand_kit_id' => ['required', 'integer'],
        ]);

        $kit = BrandKit::find($validated['brand_kit_id']);
        abort_if(! $kit, 422, 'Brand kit not found.');
        abort_unless(
            $request->user()->isSuperAdmin() || $kit->user_id === $request->user()->id,
            422,
            'Brand kit not found.',
        );

        $normalized = PublishSettings::validateAndNormalize(
            BrandKitExpandedSettings::mapToPublishSettings($kit->resolve()),
        );

        $workspace->brand_kit_id = $kit->id;
        $workspace->publish_settings = $normalized;
        $workspace->save();

        PublicFeedCache::bump($workspace);

        ActivityLogger::log(
            $request->user(),
            'workspace.brand_kit_applied',
            "Applied brand kit \"{$kit->name}\" to workspace \"{$workspace->name}\"",
            'workspace', $workspace->id, $workspace->name,
        );

        return ApiResponse::success($this->publishService->getStats($workspace), 'Brand kit applied.');
    }

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        if (! $workspace->isAccessibleBy($request->user())) {
            abort(403, 'Unauthorized');
        }
    }
}
