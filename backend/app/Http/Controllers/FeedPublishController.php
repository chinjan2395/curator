<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Workspace;
use App\Support\PublishSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedPublishController extends Controller
{
    private function authorizeWorkspace(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function ensureFeedInWorkspace(Workspace $workspace, Feed $feed): void
    {
        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    public function publish(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        if (! $feed->public_key) {
            $feed->public_key = Str::random(32);
        }

        $now = now();

        $updated = $feed->posts()
            ->where('status', 'approved')
            ->whereNull('published_at')
            ->update(['published_at' => $now]);

        $feed->last_published_at = $now;
        $feed->save();

        return response()->json([
            'message' => 'Publish complete',
            'published' => $updated,
            'public_key' => $feed->public_key,
            'last_published_at' => $feed->last_published_at,
        ]);
    }

    public function publishCode(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        if (! $feed->public_key) {
            $feed->public_key = Str::random(32);
            $feed->save();
        }

        $feed->refresh();

        $base = rtrim((string) config('app.url', ''), '/');
        $publicKey = $feed->public_key;
        // Bust browser cache when feed row (e.g. appearance settings) changes.
        $v = (string) ($feed->updated_at?->getTimestamp() ?? time());
        $cssUrl = $base.'/api/embed/'.$publicKey.'.css?v='.$v;
        $jsUrl = $base.'/api/embed/'.$publicKey.'.js?v='.$v;

        return response()->json([
            'public_key' => $publicKey,
            'public_posts_url' => $base.'/api/public/feeds/'.$publicKey.'/posts',
            'embed_js_url' => $jsUrl,
            'embed_css_url' => $cssUrl,
            'embed_html' => '<div data-curator-feed="'.$publicKey.'"></div>'."\n"
                .'<link rel="stylesheet" href="'.$cssUrl.'" />'."\n"
                .'<script src="'.$jsUrl.'"></script>',
        ]);
    }

    public function stats(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        $approved = $feed->posts()->where('status', 'approved')->count();
        $published = $feed->posts()->whereNotNull('published_at')->count();
        $pending = $feed->posts()->where('status', 'pending')->count();

        return response()->json([
            'approved' => $approved,
            'published' => $published,
            'pending' => $pending,
            'last_published_at' => $feed->last_published_at,
            'public_key' => $feed->public_key,
            'publish_settings' => PublishSettings::merge($feed->publish_settings),
        ]);
    }

    public function updateSettings(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        $patch = $request->input('publish_settings');
        if (! is_array($patch)) {
            return response()->json(['message' => 'publish_settings must be an object'], 422);
        }

        $current = PublishSettings::merge($feed->publish_settings);
        $normalized = PublishSettings::validateAndNormalize(array_replace_recursive($current, $patch));
        $feed->publish_settings = $normalized;
        $feed->save();

        return response()->json([
            'publish_settings' => $normalized,
        ]);
    }
}

