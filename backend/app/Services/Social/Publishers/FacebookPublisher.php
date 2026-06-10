<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\FacebookGraphClient;
use App\Services\Social\Support\MediaUrlClassifier;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacebookPublisher implements PublisherInterface
{
    private const GRAPH_VERSION = 'v23.0';

    public function __construct(
        private readonly FacebookGraphClient $graph = new FacebookGraphClient,
    ) {}

    public function publish(ScheduledPost $scheduledPost): array
    {
        $scheduledPost->loadMissing(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        if (! $credential || $credential->provider !== 'facebook') {
            throw new RuntimeException('A connected Facebook credential is required.');
        }

        $package = $scheduledPost->contentPackage;
        if (! $package) {
            throw new RuntimeException('Attach a content package with caption text for Facebook publishing.');
        }

        $message = ContentPackageCaptionBuilder::build($package);
        if ($message === '') {
            throw new RuntimeException('Cannot publish an empty Facebook post.');
        }

        $resolved = $this->graph->pageTokenForCredential($credential);
        $pageId = $resolved['page_id'];
        $pageToken = $resolved['page_token'];

        $mediaUrls = ContentPackageCaptionBuilder::mediaUrls($package);

        if (count($mediaUrls) === 0) {
            return $this->publishTextPost($pageId, $pageToken, $message);
        }

        if (MediaUrlClassifier::isVideo($mediaUrls[0])) {
            return $this->publishVideoPost($pageId, $pageToken, $mediaUrls[0], $message);
        }

        if (count($mediaUrls) === 1) {
            return $this->publishSinglePhotoPost($pageId, $pageToken, $mediaUrls[0], $message);
        }

        return $this->publishMultiPhotoPost($pageId, $pageToken, $mediaUrls, $message);
    }

    private function publishTextPost(string $pageId, string $pageToken, string $message): array
    {
        $response = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$pageId.'/feed',
            ['message' => $message, 'access_token' => $pageToken],
        );

        if (! $response->ok()) {
            throw new RuntimeException('Facebook publish failed: '.($response->json('error.message') ?? $response->body()));
        }

        $postId = (string) ($response->json('id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('Facebook API returned no post id.');
        }

        return $this->result($postId);
    }

    private function publishSinglePhotoPost(string $pageId, string $pageToken, string $imageUrl, string $message): array
    {
        $response = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$pageId.'/photos',
            ['url' => $imageUrl, 'message' => $message, 'access_token' => $pageToken],
        );

        if (! $response->ok()) {
            throw new RuntimeException('Facebook photo upload failed: '.($response->json('error.message') ?? $response->body()));
        }

        // Graph returns { id: photo_id, post_id: post_id } — prefer post_id for the feed URL
        $postId = (string) ($response->json('post_id') ?? $response->json('id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('Facebook API returned no post id after photo upload.');
        }

        return $this->result($postId);
    }

    /**
     * Upload up to 4 photos as unpublished staged objects, then post them together as a multi-photo post.
     *
     * @param  list<string>  $imageUrls
     */
    private function publishMultiPhotoPost(string $pageId, string $pageToken, array $imageUrls, string $message): array
    {
        $attachedMedia = [];

        foreach (array_slice($imageUrls, 0, 4) as $url) {
            $upload = Http::asForm()->post(
                'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$pageId.'/photos',
                ['url' => $url, 'published' => 'false', 'access_token' => $pageToken],
            );

            if (! $upload->ok()) {
                throw new RuntimeException(
                    'Facebook photo staging failed for '.basename((string) parse_url($url, PHP_URL_PATH)).': '
                    .($upload->json('error.message') ?? $upload->body())
                );
            }

            $photoId = (string) ($upload->json('id') ?? '');
            if ($photoId === '') {
                throw new RuntimeException('Facebook API returned no id for staged photo.');
            }

            $attachedMedia[] = ['media_fbid' => $photoId];
        }

        $response = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$pageId.'/feed',
            [
                'message' => $message,
                'attached_media' => json_encode($attachedMedia),
                'access_token' => $pageToken,
            ],
        );

        if (! $response->ok()) {
            throw new RuntimeException('Facebook multi-photo publish failed: '.($response->json('error.message') ?? $response->body()));
        }

        $postId = (string) ($response->json('id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('Facebook API returned no post id after multi-photo publish.');
        }

        return $this->result($postId);
    }

    private function publishVideoPost(string $pageId, string $pageToken, string $videoUrl, string $message): array
    {
        $response = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$pageId.'/videos',
            ['file_url' => $videoUrl, 'description' => $message, 'access_token' => $pageToken],
        );

        if (! $response->ok()) {
            throw new RuntimeException('Facebook video upload failed: '.($response->json('error.message') ?? $response->body()));
        }

        $videoId = (string) ($response->json('id') ?? '');
        if ($videoId === '') {
            throw new RuntimeException('Facebook API returned no id after video upload.');
        }

        return $this->result($videoId);
    }

    private function result(string $postId): array
    {
        return [
            'platform_post_id' => $postId,
            'platform_post_url' => 'https://www.facebook.com/'.$postId,
        ];
    }
}
