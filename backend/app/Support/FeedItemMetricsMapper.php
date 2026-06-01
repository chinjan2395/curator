<?php

namespace App\Support;

class FeedItemMetricsMapper
{
    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public static function fromYouTube(array $stats, string $videoId): array
    {
        return [
            'content_type' => 'video',
            'post_url' => 'https://www.youtube.com/watch?v='.$videoId,
            'views' => (int) ($stats['viewCount'] ?? 0),
            'likes' => (int) ($stats['likeCount'] ?? 0),
            'comments' => (int) ($stats['commentCount'] ?? 0),
            'raw_data' => ['statistics' => $stats],
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    public static function fromTwitter(array $metrics, string $tweetId): array
    {
        return [
            'content_type' => 'post',
            'post_url' => 'https://x.com/i/web/status/'.$tweetId,
            'likes' => (int) ($metrics['like_count'] ?? 0),
            'comments' => (int) ($metrics['reply_count'] ?? 0),
            'shares' => (int) ($metrics['retweet_count'] ?? 0),
            'views' => (int) ($metrics['impression_count'] ?? 0),
            'raw_data' => ['public_metrics' => $metrics],
        ];
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array<string, mixed>
     */
    public static function fromFacebook(array $insights, string $postId, string $permalink = ''): array
    {
        $likes = (int) ($insights['reactions']['summary']['total_count'] ?? $insights['likes'] ?? 0);
        $comments = (int) ($insights['comments']['summary']['total_count'] ?? $insights['comments'] ?? 0);
        $shares = (int) ($insights['shares']['count'] ?? $insights['shares'] ?? 0);

        return [
            'content_type' => 'post',
            'post_url' => $permalink ?: null,
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'raw_data' => ['insights' => $insights],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function fromInstagram(array $item): array
    {
        return [
            'content_type' => ($item['media_type'] ?? '') === 'VIDEO' ? 'video' : 'image',
            'post_url' => $item['permalink'] ?? null,
            'likes' => (int) ($item['like_count'] ?? 0),
            'comments' => (int) ($item['comments_count'] ?? 0),
            'views' => (int) ($item['video_views'] ?? $item['views'] ?? 0),
            'raw_data' => ['media' => $item],
        ];
    }

    /**
     * @param  array<string, mixed>  $video
     * @return array<string, mixed>
     */
    public static function fromTikTok(array $video): array
    {
        $stats = $video['statistics'] ?? $video;

        return [
            'content_type' => 'video',
            'post_url' => $video['share_url'] ?? null,
            'likes' => (int) ($stats['like_count'] ?? $stats['digg_count'] ?? 0),
            'comments' => (int) ($stats['comment_count'] ?? 0),
            'shares' => (int) ($stats['share_count'] ?? 0),
            'views' => (int) ($stats['view_count'] ?? $stats['play_count'] ?? 0),
            'raw_data' => ['statistics' => $stats],
        ];
    }

    /**
     * @param  array<string, mixed>  $thread
     * @return array<string, mixed>
     */
    public static function fromThreads(array $thread): array
    {
        return [
            'content_type' => 'post',
            'post_url' => $thread['permalink'] ?? null,
            'likes' => (int) ($thread['like_count'] ?? 0),
            'comments' => (int) ($thread['reply_count'] ?? 0),
            'views' => (int) ($thread['views'] ?? 0),
            'raw_data' => ['thread' => $thread],
        ];
    }

    /** @return array<string, mixed> */
    public static function fromRss(): array
    {
        return [
            'content_type' => 'article',
            'likes' => 0,
            'comments' => 0,
            'views' => 0,
        ];
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    public static function hashtagsFromText(string $text, array $extra = []): array
    {
        preg_match_all('/#(\w+)/u', $text, $matches);
        $found = array_map(static fn ($t) => '#'.$t, $matches[1] ?? []);

        return ['hashtags' => array_values(array_unique(array_merge($found, $extra)))];
    }
}
