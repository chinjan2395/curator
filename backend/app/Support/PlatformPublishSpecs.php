<?php

namespace App\Support;

/**
 * Native publish content types per platform.
 *
 * Specs align with Curator's publishers and each provider's official API docs:
 * - X: https://developer.x.com/en/docs/twitter-api/tweets/manage-tweets/introduction
 * - Facebook Pages: https://developers.facebook.com/docs/pages-api/posts
 * - Instagram: https://developers.facebook.com/docs/instagram-platform/content-publishing
 * - TikTok: https://developers.tiktok.com/doc/content-posting-api-get-started
 * - Threads: https://developers.facebook.com/docs/threads/posts
 * - LinkedIn: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api
 */
class PlatformPublishSpecs
{
    /** @var array<string, string> */
    private const ALIASES = [
        'x' => 'twitter',
    ];

    public static function normalize(string $platform): string
    {
        $key = strtolower(trim($platform));

        return self::ALIASES[$key] ?? $key;
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'twitter' => self::twitter(),
            'facebook' => self::facebook(),
            'instagram' => self::instagram(),
            'tiktok' => self::tiktok(),
            'threads' => self::threads(),
            'linkedin' => self::linkedin(),
            'youtube' => self::youtube(),
            'rss' => self::rss(),
        ];
    }

    /** @return array<string, array{enabled: bool, reason: string|null}> */
    public static function nativeMatrix(): array
    {
        $matrix = [];

        foreach (self::all() as $platform => $spec) {
            $matrix[$platform] = [
                'enabled' => (bool) ($spec['native_publish'] ?? false),
                'reason' => $spec['native_publish']
                    ? ($spec['native_note'] ?? null)
                    : ($spec['embed_note'] ?? 'Not available for native scheduling'),
            ];
        }

        return $matrix;
    }

    /** @return array<string, mixed>|null */
    public static function forPlatform(string $platform): ?array
    {
        $key = self::normalize($platform);

        return self::all()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    private static function twitter(): array
    {
        return [
            'native_publish' => true,
            'embed_only' => false,
            'summary' => 'Text tweets with up to 4 images via X API v2.',
            'native_note' => null,
            'content_types' => [
                self::type('text', 'Text post', true, 'Up to 280 characters', 'Caption required'),
                self::type('image', 'Images', true, 'Up to 4 images per tweet', 'Public HTTPS image URLs; JPG, PNG, GIF, WEBP'),
                self::type('video', 'Video', false, null, 'Not supported in Curator yet — use images or text'),
                self::type('carousel', 'Carousel', false, null, 'Use multiple images (max 4) instead'),
            ],
            'requirements' => [
                'OAuth scope: tweet.write (reconnect after enabling)',
                'Caption text required',
            ],
            'media_rules' => [
                'Image URLs must be publicly reachable over HTTPS',
                'Videos are not uploaded natively yet',
            ],
            'docs_url' => 'https://developer.x.com/en/docs/twitter-api/tweets/manage-tweets/introduction',
            'docs_label' => 'X API — Manage Tweets',
        ];
    }

    /** @return array<string, mixed> */
    private static function facebook(): array
    {
        return [
            'native_publish' => true,
            'embed_only' => false,
            'summary' => 'Page feed posts — text, single/multi photo, or video.',
            'native_note' => null,
            'content_types' => [
                self::type('text', 'Text post', true, 'No hard API caption limit in Curator', 'Caption required'),
                self::type('image', 'Single photo', true, '1 image URL', 'Public HTTPS image URL in media_urls'),
                self::type('carousel', 'Multi-photo', true, 'Up to 4 images', 'Multiple HTTPS image URLs'),
                self::type('video', 'Video', true, '1 video URL', 'Public HTTPS video URL; published to Page feed'),
            ],
            'requirements' => [
                'Facebook Page connected (not personal profile)',
                'OAuth scope: pages_manage_posts',
            ],
            'media_rules' => [
                'Graph API pulls media from public URLs',
                'First media URL determines post type (image vs video)',
            ],
            'docs_url' => 'https://developers.facebook.com/docs/pages-api/posts',
            'docs_label' => 'Meta — Page Posts API',
        ];
    }

    /** @return array<string, mixed> */
    private static function instagram(): array
    {
        return [
            'native_publish' => true,
            'embed_only' => false,
            'summary' => 'Feed image, carousel, or Reels — media required.',
            'native_note' => 'Requires public HTTPS image or video URL on content package',
            'content_types' => [
                self::type('text', 'Text only', false, null, 'Instagram requires at least one image or video'),
                self::type('image', 'Single image', true, '1 image', 'Public HTTPS image URL'),
                self::type('carousel', 'Carousel', true, '2–10 images', 'Images only in carousel; no mixed video carousel'),
                self::type('video', 'Reels', true, '1 video', 'Published as REELS via Content Publishing API'),
            ],
            'requirements' => [
                'Instagram Business/Creator account linked to a Facebook Page',
                'OAuth scope: instagram_content_publish',
                'Caption up to 2,200 characters',
            ],
            'media_rules' => [
                'All media URLs must be public HTTPS (Meta servers fetch the file)',
                'localhost or private URLs will fail',
            ],
            'docs_url' => 'https://developers.facebook.com/docs/instagram-platform/content-publishing',
            'docs_label' => 'Meta — Instagram Content Publishing',
        ];
    }

    /** @return array<string, mixed> */
    private static function tiktok(): array
    {
        return [
            'native_publish' => true,
            'embed_only' => false,
            'summary' => 'Video or photo post via Content Posting API (PULL_FROM_URL).',
            'native_note' => 'Requires public HTTPS media URL; verify URL domain in TikTok developer portal',
            'content_types' => [
                self::type('text', 'Text only', false, null, 'Caption/title required with media'),
                self::type('video', 'Video', true, '1 video URL', 'MP4 and other supported formats via HTTPS'),
                self::type('image', 'Photo post', true, 'Up to 35 images', 'Multiple HTTPS image URLs for photo mode'),
                self::type('carousel', 'Photo carousel', true, 'Up to 35 images', 'Use multiple image URLs; one video URL per video post'),
            ],
            'requirements' => [
                'OAuth scope: video.publish',
                'Title/caption up to 2,200 characters',
                'Domain of media URLs must be verified in TikTok developer app',
            ],
            'media_rules' => [
                'PULL_FROM_URL — TikTok fetches media from your HTTPS link',
                'One video per post; multiple images for photo posts only',
            ],
            'docs_url' => 'https://developers.tiktok.com/doc/content-posting-api-get-started',
            'docs_label' => 'TikTok — Content Posting API',
        ];
    }

    /** @return array<string, mixed> */
    private static function threads(): array
    {
        return [
            'native_publish' => true,
            'embed_only' => false,
            'summary' => 'Text, image, video, or carousel Threads posts.',
            'native_note' => 'Text, image, or HTTPS video via content package; threads_content_publish scope',
            'content_types' => [
                self::type('text', 'Text post', true, 'Up to 500 characters', 'Text-only or text + media'),
                self::type('image', 'Image', true, '1 image', 'Public HTTPS image URL'),
                self::type('video', 'Video', true, '1 video', 'Public HTTPS video URL'),
                self::type('carousel', 'Carousel', true, 'Up to 20 items', 'Mix of images and videos in carousel'),
            ],
            'requirements' => [
                'OAuth scope: threads_content_publish',
                'Caption or media required',
            ],
            'media_rules' => [
                'Media URLs must be public HTTPS',
            ],
            'docs_url' => 'https://developers.facebook.com/docs/threads/posts',
            'docs_label' => 'Meta — Threads Posts API',
        ];
    }

    /** @return array<string, mixed> */
    private static function linkedin(): array
    {
        return [
            'native_publish' => true,
            'embed_only' => false,
            'summary' => 'Member posts — text, images, or article link.',
            'native_note' => 'Text or article URL in media_urls; w_member_social scope',
            'content_types' => [
                self::type('text', 'Text post', true, 'Up to 3,000 characters', 'Caption required'),
                self::type('image', 'Images', true, 'Up to 9 images', 'Public HTTPS images uploaded to LinkedIn'),
                self::type('article', 'Article / link', true, '1 URL', 'Set content type to article or paste article HTTPS URL'),
                self::type('video', 'Video', false, null, 'Not supported in Curator yet'),
            ],
            'requirements' => [
                'OAuth scope: w_member_social',
                'Share on LinkedIn product enabled in developer app',
            ],
            'media_rules' => [
                'Images are downloaded and re-uploaded to LinkedIn',
                'Article posts need a public HTTPS page URL (not an image file)',
            ],
            'docs_url' => 'https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api',
            'docs_label' => 'LinkedIn — Posts API',
        ];
    }

    /** @return array<string, mixed> */
    private static function youtube(): array
    {
        return [
            'native_publish' => false,
            'embed_only' => true,
            'summary' => 'Sync channel uploads and publish to embed widget only.',
            'embed_note' => 'Embed publishing only — use workspace Publish for your site widget',
            'content_types' => [
                self::type('video', 'YouTube videos', true, 'Via channel sync', 'Imported from your connected channel — not uploaded via Curator'),
                self::type('text', 'Native post', false, null, 'YouTube Data API upload not integrated'),
            ],
            'requirements' => [
                'Use feed sync + Curator approve/publish for embed',
            ],
            'media_rules' => [],
            'docs_url' => 'https://developers.google.com/youtube/v3/docs/videos/insert',
            'docs_label' => 'YouTube Data API — Videos',
        ];
    }

    /** @return array<string, mixed> */
    private static function rss(): array
    {
        return [
            'native_publish' => false,
            'embed_only' => true,
            'summary' => 'RSS/Atom feed import for embed curation — no native outbound publish.',
            'embed_note' => 'Feed sync and embed only',
            'content_types' => [
                self::type('article', 'Feed items', true, 'From RSS/Atom URL', 'Synced into Curator for approval and embed'),
                self::type('text', 'Native post', false, null, 'RSS feeds cannot be posted to natively'),
            ],
            'requirements' => [
                'Provide a valid RSS 2.0 or Atom feed URL',
            ],
            'media_rules' => [],
            'docs_url' => 'https://www.rssboard.org/rss-specification',
            'docs_label' => 'RSS 2.0 Specification',
        ];
    }

    /**
     * @return array{id: string, label: string, supported: bool, limits: string|null, note: string|null}
     */
    private static function type(
        string $id,
        string $label,
        bool $supported,
        ?string $limits,
        ?string $note,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'supported' => $supported,
            'limits' => $limits,
            'note' => $note,
        ];
    }
}
