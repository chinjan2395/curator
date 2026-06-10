<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\MediaUrlClassifier;
use App\Services\Social\Support\ThreadsPublishingClient;
use RuntimeException;

class ThreadsPublisher implements PublisherInterface
{
    private const MAX_TEXT_LENGTH = 500;

    private const MAX_CAROUSEL_ITEMS = 20;

    public function __construct(
        private readonly ThreadsPublishingClient $client = new ThreadsPublishingClient,
    ) {}

    public function publish(ScheduledPost $scheduledPost): array
    {
        $scheduledPost->loadMissing(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        if (! $credential || $credential->provider !== 'threads') {
            throw new RuntimeException('A connected Threads credential is required.');
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            throw new RuntimeException('Threads token has expired. Reconnect the account in Credentials.');
        }

        $package = $scheduledPost->contentPackage;
        if (! $package) {
            throw new RuntimeException('Schedule a content package for Threads publishing.');
        }

        $text = ContentPackageCaptionBuilder::build($package, self::MAX_TEXT_LENGTH);
        $mediaUrls = ContentPackageCaptionBuilder::mediaUrls($package);

        foreach ($mediaUrls as $url) {
            if (! MediaUrlClassifier::isHttpsUrl($url)) {
                throw new RuntimeException('Threads media posts require public HTTPS URLs in content package media_urls.');
            }
        }

        if ($mediaUrls === [] && $text === '') {
            throw new RuntimeException('Threads requires caption text or a public HTTPS image/video URL in media_urls.');
        }

        $userId = $this->client->resolveUserId($credential, $token);

        if (count($mediaUrls) >= 2) {
            $containerId = $this->createCarouselContainer($userId, $token, $mediaUrls, $text, $package);
        } else {
            $mediaUrl = $mediaUrls[0] ?? null;
            $mediaType = $this->resolveMediaType($mediaUrl, (string) ($package->content_type ?? ''));
            $containerParams = $this->buildContainerParams($mediaType, $text, $mediaUrl);
            $container = $this->client->createContainer($userId, $token, $containerParams);
            $containerId = $container['creation_id'];

            if ($mediaType !== 'TEXT') {
                $this->client->waitUntilReady($token, $containerId);
            }
        }

        $published = $this->client->publishContainer($userId, $token, $containerId);
        $postId = $published['platform_post_id'];
        $permalink = $this->client->fetchPermalink($token, $postId);

        return [
            'platform_post_id' => $postId,
            'platform_post_url' => $permalink,
        ];
    }

    /**
     * @param  list<string>  $mediaUrls
     */
    private function createCarouselContainer(
        string $userId,
        string $token,
        array $mediaUrls,
        string $text,
        $package,
    ): string {
        $childIds = [];

        foreach (array_slice($mediaUrls, 0, self::MAX_CAROUSEL_ITEMS) as $mediaUrl) {
            $mediaType = $this->resolveMediaType($mediaUrl, (string) ($package->content_type ?? ''));
            $params = [
                'media_type' => $mediaType,
                'is_carousel_item' => 'true',
            ];

            if ($mediaType === 'IMAGE') {
                $params['image_url'] = $mediaUrl;
            } else {
                $params['video_url'] = $mediaUrl;
            }

            $child = $this->client->createContainer($userId, $token, $params);
            $this->client->waitUntilReady($token, $child['creation_id']);
            $childIds[] = $child['creation_id'];
        }

        $params = [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
        ];

        if ($text !== '') {
            $params['text'] = $text;
        }

        $container = $this->client->createContainer($userId, $token, $params);
        $this->client->waitUntilReady($token, $container['creation_id']);

        return $container['creation_id'];
    }

    /** @return array<string, string> */
    private function buildContainerParams(string $mediaType, string $text, ?string $mediaUrl): array
    {
        $params = ['media_type' => $mediaType];

        if ($text !== '') {
            $params['text'] = $text;
        }

        if ($mediaType === 'IMAGE' && $mediaUrl) {
            $params['image_url'] = $mediaUrl;
        }

        if ($mediaType === 'VIDEO' && $mediaUrl) {
            $params['video_url'] = $mediaUrl;
        }

        return $params;
    }

    private function resolveMediaType(?string $mediaUrl, string $contentType): string
    {
        if (! $mediaUrl) {
            return 'TEXT';
        }

        if (MediaUrlClassifier::isVideoContentType($contentType) || MediaUrlClassifier::isVideo($mediaUrl)) {
            return 'VIDEO';
        }

        return 'IMAGE';
    }
}
