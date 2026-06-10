<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\LinkedInPublishingClient;
use App\Services\Social\Support\MediaUrlClassifier;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LinkedInPublisher implements PublisherInterface
{
    private const MAX_COMMENTARY_LENGTH = 3000;

    private const MAX_IMAGES = 9;

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
        $mediaUrls = ContentPackageCaptionBuilder::mediaUrls($package);

        if ($mediaUrls === []) {
            $postId = $this->client->createTextPost($token, $authorUrn, $commentary);
        } elseif ($this->containsVideo($mediaUrls)) {
            throw new RuntimeException(
                'LinkedIn video upload is not yet supported. Use text-only posts, image URLs, or an article link in media_urls.',
            );
        } elseif ($this->areImageUrls($mediaUrls)) {
            $postId = $this->publishImagePost($token, $authorUrn, $commentary, $mediaUrls);
        } elseif (count($mediaUrls) === 1 && $this->isArticleUrl($mediaUrls[0], (string) ($package->content_type ?? ''))) {
            $postId = $this->client->createArticlePost(
                $token,
                $authorUrn,
                $commentary,
                $mediaUrls[0],
                $this->articleTitle($commentary),
                mb_substr($commentary, 0, 200),
            );
        } else {
            $postId = $this->client->createTextPost($token, $authorUrn, $commentary);
        }

        return [
            'platform_post_id' => $postId,
            'platform_post_url' => $this->buildPostUrl($postId),
        ];
    }

    /**
     * @param  list<string>  $mediaUrls
     */
    private function publishImagePost(string $token, string $authorUrn, string $commentary, array $mediaUrls): string
    {
        $imageUrns = [];

        foreach (array_slice($mediaUrls, 0, self::MAX_IMAGES) as $imageUrl) {
            if (! MediaUrlClassifier::isHttpsUrl($imageUrl)) {
                throw new RuntimeException('LinkedIn image URLs must be public HTTPS links.');
            }

            $download = Http::timeout(60)->get($imageUrl);
            if (! $download->ok()) {
                throw new RuntimeException('Could not download image for LinkedIn upload: '.$imageUrl);
            }

            $init = $this->client->initializeImageUpload($token, $authorUrn);
            $this->client->uploadImageBinary($init['upload_url'], $download->body(), $token);
            $imageUrns[] = $init['image_urn'];
        }

        return $this->client->createImagePost($token, $authorUrn, $commentary, $imageUrns);
    }

    /**
     * @param  list<string>  $mediaUrls
     */
    private function areImageUrls(array $mediaUrls): bool
    {
        foreach ($mediaUrls as $url) {
            if (! MediaUrlClassifier::looksLikeImageAsset($url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $mediaUrls
     */
    private function containsVideo(array $mediaUrls): bool
    {
        foreach ($mediaUrls as $url) {
            if (MediaUrlClassifier::isVideo($url)) {
                return true;
            }
        }

        return false;
    }

    private function articleTitle(string $commentary): string
    {
        $line = trim(strtok($commentary, "\n"));

        return mb_substr($line !== '' ? $line : $commentary, 0, 200);
    }

    private function isArticleUrl(string $url, string $contentType): bool
    {
        if ($contentType === 'article' || $contentType === 'link') {
            return MediaUrlClassifier::isHttpsUrl($url);
        }

        if (MediaUrlClassifier::looksLikeImageAsset($url) || MediaUrlClassifier::isVideo($url)) {
            return false;
        }

        return MediaUrlClassifier::isHttpsUrl($url);
    }

    private function buildPostUrl(string $postUrn): ?string
    {
        if (preg_match('/urn:li:(?:share|ugcPost):(\d+)/', $postUrn, $matches)) {
            return 'https://www.linkedin.com/feed/update/urn:li:share:'.$matches[1];
        }

        return null;
    }
}
