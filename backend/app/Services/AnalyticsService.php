<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\EmbedPostEvent;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function overview(User $user): array
    {
        $workspaceIds = Workspace::where('owner_id', $user->id)->pluck('id');
        $feedIds = DB::table('feeds')->whereIn('workspace_id', $workspaceIds)->pluck('id');

        $posts = Post::query()->whereIn('feed_id', $feedIds);
        $embedClicks = $this->embedClickQuery($workspaceIds);

        return [
            'total_posts' => (clone $posts)->count(),
            'published_embed' => (clone $posts)->whereNotNull('published_at')->count(),
            'total_likes' => (int) (clone $posts)->sum('likes'),
            'total_comments' => (int) (clone $posts)->sum('comments'),
            'total_views' => (int) (clone $posts)->sum('views'),
            'total_embed_clicks' => (int) (clone $embedClicks)->count(),
            'engagement_rate' => $this->engagementRate($posts),
            'follower_total' => (int) SocialCredential::where('user_id', $user->id)->sum('follower_count'),
            'scheduled_upcoming' => ScheduledPost::where('user_id', $user->id)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>', now())
                ->count(),
            'best_post' => (clone $posts)->orderByDesc('likes')->first(['id', 'title', 'likes', 'thumbnail_url']),
            'top_embed_clicked_posts' => $this->topEmbedClickedPosts($workspaceIds),
        ];
    }

    public function byPlatform(User $user, string $platform): array
    {
        $workspaceIds = Workspace::where('owner_id', $user->id)->pluck('id');
        $feedIds = DB::table('feeds')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('type', $platform)
            ->pluck('id');

        $posts = Post::query()->whereIn('feed_id', $feedIds);
        $embedClicks = EmbedPostEvent::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->whereIn('post_id', (clone $posts)->select('id'))
            ->where('event_type', EmbedPostEvent::TYPE_POST_CLICK);

        return [
            'platform' => $platform,
            'post_count' => $posts->count(),
            'likes' => (int) $posts->sum('likes'),
            'comments' => (int) $posts->sum('comments'),
            'views' => (int) $posts->sum('views'),
            'embed_clicks' => (int) $embedClicks->count(),
            'engagement_rate' => $this->engagementRate($posts),
        ];
    }

    public function insights(User $user): array
    {
        $overview = $this->overview($user);
        $insights = [];

        if ($overview['total_views'] > 0 && $overview['total_likes'] > 0) {
            $ratio = round($overview['total_likes'] / max(1, $overview['total_views']) * 100, 1);
            $insights[] = "Your average like-to-view ratio is {$ratio}%.";
        }

        if ($overview['scheduled_upcoming'] > 0) {
            $insights[] = "You have {$overview['scheduled_upcoming']} posts scheduled.";
        }

        if ($overview['best_post']) {
            $insights[] = 'Your top post by likes is: '.$overview['best_post']->title;
        }

        return $insights;
    }

    public function campaignPerformance(User $user): array
    {
        return Campaign::query()
            ->where('user_id', $user->id)
            ->withCount(['contentPackages'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(static function (Campaign $campaign) {
                $approved = ContentPackage::where('campaign_id', $campaign->id)
                    ->where('status', 'approved')
                    ->count();

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'packages' => $campaign->content_packages_count,
                    'approved' => $approved,
                ];
            })
            ->all();
    }

    public function platformTimeSeries(User $user): array
    {
        $workspaceIds = Workspace::where('owner_id', $user->id)->pluck('id');

        return DB::table('posts')
            ->join('feeds', 'feeds.id', '=', 'posts.feed_id')
            ->whereIn('feeds.workspace_id', $workspaceIds)
            ->selectRaw('feeds.type as platform, DATE(posts.posted_at) as day, SUM(posts.likes) as likes, SUM(posts.views) as views')
            ->whereNotNull('posts.posted_at')
            ->where('posts.posted_at', '>=', now()->subDays(30))
            ->groupBy('feeds.type', DB::raw('DATE(posts.posted_at)'))
            ->orderBy('day')
            ->get()
            ->groupBy('platform')
            ->map(static fn ($rows) => $rows->values())
            ->all();
    }

    private function engagementRate($query): float
    {
        $views = (int) (clone $query)->sum('views');
        if ($views === 0) {
            return 0.0;
        }

        $engagement = (int) (clone $query)->sum('likes')
            + (int) (clone $query)->sum('comments')
            + (int) (clone $query)->sum('shares');

        return round($engagement / $views * 100, 2);
    }

    /** @param  \Illuminate\Support\Collection<int, int|string>  $workspaceIds */
    private function embedClickQuery($workspaceIds)
    {
        return EmbedPostEvent::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where('event_type', EmbedPostEvent::TYPE_POST_CLICK);
    }

    /** @param  \Illuminate\Support\Collection<int, int|string>  $workspaceIds */
    private function topEmbedClickedPosts($workspaceIds, int $limit = 5): array
    {
        return DB::table('embed_post_events')
            ->join('posts', 'posts.id', '=', 'embed_post_events.post_id')
            ->whereIn('embed_post_events.workspace_id', $workspaceIds)
            ->where('embed_post_events.event_type', EmbedPostEvent::TYPE_POST_CLICK)
            ->groupBy('embed_post_events.post_id', 'posts.title', 'posts.thumbnail_url')
            ->selectRaw('embed_post_events.post_id as id, posts.title, posts.thumbnail_url, COUNT(*) as clicks')
            ->orderByDesc('clicks')
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'thumbnail_url' => $row->thumbnail_url,
                'clicks' => (int) $row->clicks,
            ])
            ->all();
    }
}
