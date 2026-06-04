<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\ThreadsPublishingClient;
use RuntimeException;

class ThreadsPublisher implements PublisherInterface
{
    private const MAX_TEXT_LENGTH = 500;

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
        $mediaUrl = ContentPackageCaptionBuilder::firstMediaUrl($package);
        $mediaType = $this->resolveMediaType($mediaUrl, (string) ($package->content_type ?? ''));

        if ($mediaType === 'TEXT' && $text === '') {
            throw new RuntimeException('Threads requires caption text or a public HTTPS image/video URL in media_urls.');
        }

        if ($mediaType !== 'TEXT' && (! $mediaUrl || ! $this->isHttpsUrl($mediaUrl))) {
            throw new RuntimeException('Threads media posts require a public HTTPS URL in content package media_urls.');
        }

        $userId = $this->client->resolveUserId($credential, $token);
        $containerParams = $this->buildContainerParams($mediaType, $text, $mediaUrl);

        $container = $this->client->createContainer($userId, $token, $containerParams);

        if ($mediaType !== 'TEXT') {
            $this->client->waitUntilReady($token, $container['creation_id']);
        }

        $published = $this->client->publishContainer($userId, $token, $container['creation_id']);
        $postId = $published['platform_post_id'];
        $permalink = $this->client->fetchPermalink($token, $postId);

        return [
            'platform_post_id' => $postId,
            'platform_post_url' => $permalink,
        ];
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

        if ($contentType === 'video' || $this->looksLikeVideoUrl($mediaUrl)) {
            return 'VIDEO';
        }

        return 'IMAGE';
    }

    private function looksLikeVideoUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return (bool) preg_match('/\.(mp4|mov|webm|m4v|avi)(\?.*)?$/', $path);
    }

    private function isHttpsUrl(string $url): bool
    {
        return (bool) preg_match('#^https://#i', $url);
    }
}
