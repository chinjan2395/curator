<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\FacebookGraphClient;
use App\Services\Social\Support\InstagramPublishingClient;
use App\Services\Social\Support\MediaUrlClassifier;
use RuntimeException;

class InstagramPublisher implements PublisherInterface
{
    private const MAX_CAROUSEL_ITEMS = 10;

    public function __construct(
        private readonly FacebookGraphClient $graph = new FacebookGraphClient,
        private readonly InstagramPublishingClient $client = new InstagramPublishingClient,
    ) {}

    public function publish(ScheduledPost $scheduledPost): array
    {
        $scheduledPost->loadMissing(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        if (! $credential || $credential->provider !== 'instagram') {
            throw new RuntimeException('A connected Instagram credential is required.');
        }

        $package = $scheduledPost->contentPackage;
        if (! $package) {
            throw new RuntimeException('Attach a content package for Instagram publishing.');
        }

        $mediaUrls = ContentPackageCaptionBuilder::mediaUrls($package);
        if ($mediaUrls === []) {
            throw new RuntimeException('Instagram requires at least one public HTTPS image or video URL in media_urls.');
        }

        foreach ($mediaUrls as $url) {
            if (! MediaUrlClassifier::isHttpsUrl($url)) {
                throw new RuntimeException('Instagram media URLs must be public HTTPS links.');
            }
        }

        $caption = ContentPackageCaptionBuilder::build($package, 2200);
        $ctx = $this->graph->instagramContext($credential);
        $igUserId = $ctx['ig_user_id'];
        $pageToken = $ctx['page_token'];

        if (count($mediaUrls) === 1 && MediaUrlClassifier::isVideo($mediaUrls[0])) {
            $containerId = $this->publishReelsContainer($igUserId, $pageToken, $mediaUrls[0], $caption);
        } elseif (count($mediaUrls) >= 2) {
            MediaUrlClassifier::assertNoVideos($mediaUrls, 'Instagram carousel');
            $containerId = $this->publishCarouselContainer($igUserId, $pageToken, $mediaUrls, $caption);
        } else {
            $containerId = $this->publishImageContainer($igUserId, $pageToken, $mediaUrls[0], $caption);
        }

        $published = $this->client->publishContainer($igUserId, $pageToken, $containerId);
        $mediaId = $published['platform_post_id'];
        $permalink = $this->client->fetchPermalink($mediaId, $pageToken);

        return [
            'platform_post_id' => $mediaId,
            'platform_post_url' => $permalink,
        ];
    }

    private function publishImageContainer(string $igUserId, string $pageToken, string $imageUrl, string $caption): string
    {
        $container = $this->client->createContainer($igUserId, $pageToken, [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ]);

        return $container['creation_id'];
    }

    private function publishReelsContainer(string $igUserId, string $pageToken, string $videoUrl, string $caption): string
    {
        $container = $this->client->createContainer($igUserId, $pageToken, [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => $caption,
        ]);

        $this->client->waitUntilReady($pageToken, $container['creation_id']);

        return $container['creation_id'];
    }

    /**
     * @param  list<string>  $imageUrls
     */
    private function publishCarouselContainer(string $igUserId, string $pageToken, array $imageUrls, string $caption): string
    {
        $childIds = [];

        foreach (array_slice($imageUrls, 0, self::MAX_CAROUSEL_ITEMS) as $imageUrl) {
            $child = $this->client->createContainer($igUserId, $pageToken, [
                'image_url' => $imageUrl,
                'is_carousel_item' => 'true',
            ]);
            $childIds[] = $child['creation_id'];
        }

        $container = $this->client->createContainer($igUserId, $pageToken, [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
            'caption' => $caption,
        ]);

        return $container['creation_id'];
    }
}
