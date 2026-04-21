<?php

namespace App\Http\Controllers;

use App\Models\Post;
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

    public function publish(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        if (! $workspace->public_key) {
            $workspace->public_key = Str::random(32);
        }

        $now = now();

        $updated = Post::query()
            ->whereIn('feed_id', $workspace->feeds()->select('id'))
            ->where('status', 'approved')
            ->whereNull('published_at')
            ->update(['published_at' => $now]);

        $workspace->last_published_at = $now;
        $workspace->save();

        return response()->json([
            'message' => 'Publish complete',
            'published' => $updated,
            'public_key' => $workspace->public_key,
            'last_published_at' => $workspace->last_published_at,
        ]);
    }

    public function publishCode(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        if (! $workspace->public_key) {
            $workspace->public_key = Str::random(32);
            $workspace->save();
        }

        $workspace->refresh();

        $base = rtrim((string) config('app.url', ''), '/');
        $publicKey = $workspace->public_key;
        // Bust browser cache when feed row (e.g. appearance settings) changes.
        $v = (string) ($workspace->updated_at?->getTimestamp() ?? time());
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

    public function stats(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $workspacePostQuery = Post::query()->whereIn('feed_id', $workspace->feeds()->select('id'));

        $approved = (clone $workspacePostQuery)->where('status', 'approved')->count();
        $published = (clone $workspacePostQuery)->whereNotNull('published_at')->count();
        $pending = (clone $workspacePostQuery)->where('status', 'pending')->count();

        return response()->json([
            'approved' => $approved,
            'published' => $published,
            'pending' => $pending,
            'last_published_at' => $workspace->last_published_at,
            'public_key' => $workspace->public_key,
            'publish_settings' => PublishSettings::merge($workspace->publish_settings),
        ]);
    }

    public function updateSettings(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $patch = $request->input('publish_settings');
        if (! is_array($patch)) {
            return response()->json(['message' => 'publish_settings must be an object'], 422);
        }

        $current = PublishSettings::merge($workspace->publish_settings);
        $normalized = PublishSettings::validateAndNormalize(array_replace_recursive($current, $patch));
        $workspace->publish_settings = $normalized;
        $workspace->save();

        return response()->json([
            'publish_settings' => $normalized,
        ]);
    }
}

