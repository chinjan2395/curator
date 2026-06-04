<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\LinkedInPublishingClient;
use RuntimeException;

class LinkedInPublisher implements PublisherInterface
{
    private const MAX_COMMENTARY_LENGTH = 3000;

    public function __construct(
        private readonly LinkedInPublishingClient $client = new LinkedInPublishingClient,
    ) {}

    public function publish(ScheduledPost $scheduledPost): array
    {
        $scheduledPost->loadMissing(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        if (! $credential || $credential->provider !== 'linkedin') {
            throw new RuntimeException('A connected LinkedIn credential is required.');
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            throw new RuntimeException('LinkedIn token has expired. Reconnect the account in Credentials.');
        }

        $package = $scheduledPost->contentPackage;
        if (! $package) {
            throw new RuntimeException('Schedule a content package for LinkedIn publishing.');
        }

        $commentary = ContentPackageCaptionBuilder::build($package, self::MAX_COMMENTARY_LENGTH);
        if ($commentary === '') {
            throw new RuntimeException('Cannot publish an empty LinkedIn post. Add a caption to the content package.');
        }

        $personId = trim((string) ($credential->account_id ?? ''));
        if ($personId === '') {
            $info = $this->client->fetchUserInfo($token);
            $personId = (string) ($info['sub'] ?? '');
        }
        if ($personId === '') {
            throw new RuntimeException('Could not resolve LinkedIn member id. Reconnect LinkedIn in Credentials.');
        }

        $authorUrn = $this->client->personUrn($personId);
        $mediaUrl = ContentPackageCaptionBuilder::firstMediaUrl($package);

        if ($mediaUrl && $this->isArticleUrl($mediaUrl, (string) ($package->content_type ?? ''))) {
            $postId = $this->client->createArticlePost(
                $token,
                $authorUrn,
                $commentary,
                $mediaUrl,
                $this->articleTitle($commentary),
                mb_substr($commentary, 0, 200),
            );
        } elseif ($mediaUrl && $this->isHttpsUrl($mediaUrl)) {
            throw new RuntimeException(
                'LinkedIn image/video posts require asset upload via the Images/Videos API. Use text-only posts or put an article URL in media_urls.',
            );
        } else {
            $postId = $this->client->createTextPost($token, $authorUrn, $commentary);
        }

        return [
            'platform_post_id' => $postId,
            'platform_post_url' => $this->buildPostUrl($postId),
        ];
    }

    private function articleTitle(string $commentary): string
    {
        $line = trim(strtok($commentary, "\n"));

        return mb_substr($line !== '' ? $line : $commentary, 0, 200);
    }

    private function isArticleUrl(string $url, string $contentType): bool
    {
        if ($contentType === 'article' || $contentType === 'link') {
            return $this->isHttpsUrl($url);
        }

        if ($this->looksLikeMediaAsset($url)) {
            return false;
        }

        return $this->isHttpsUrl($url);
    }

    private function looksLikeMediaAsset(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|mp4|mov|webm|m4v|avi|pdf)(\?.*)?$/', $path);
    }

    private function buildPostUrl(string $postUrn): ?string
    {
        if (preg_match('/urn:li:(?:share|ugcPost):(\d+)/', $postUrn, $matches)) {
            return 'https://www.linkedin.com/feed/update/urn:li:share:'.$matches[1];
        }

        return null;
    }

    private function isHttpsUrl(string $url): bool
    {
        return (bool) preg_match('#^https://#i', $url);
    }
}
