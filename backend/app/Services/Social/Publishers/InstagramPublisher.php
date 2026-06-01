<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\FacebookGraphClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramPublisher implements PublisherInterface
{
    private const GRAPH_VERSION = 'v23.0';

    public function __construct(
        private readonly FacebookGraphClient $graph = new FacebookGraphClient,
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

        $imageUrl = ContentPackageCaptionBuilder::firstMediaUrl($package);
        if (! $imageUrl) {
            throw new RuntimeException('Instagram requires an image URL in the content package media_urls.');
        }

        $caption = ContentPackageCaptionBuilder::build($package, 2200);
        $ctx = $this->graph->instagramContext($credential);

        $create = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$ctx['ig_user_id'].'/media',
            [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $ctx['page_token'],
            ],
        );

        if (! $create->ok()) {
            $err = $create->json('error.message') ?? $create->body();
            throw new RuntimeException('Instagram media container failed: '.$err);
        }

        $containerId = (string) ($create->json('id') ?? '');
        if ($containerId === '') {
            throw new RuntimeException('Instagram API returned no container id.');
        }

        $publish = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$ctx['ig_user_id'].'/media_publish',
            [
                'creation_id' => $containerId,
                'access_token' => $ctx['page_token'],
            ],
        );

        if (! $publish->ok()) {
            $err = $publish->json('error.message') ?? $publish->body();
            throw new RuntimeException('Instagram publish failed: '.$err);
        }

        $mediaId = (string) ($publish->json('id') ?? $containerId);
        $permalink = $this->fetchPermalink($mediaId, $ctx['page_token']);

        return [
            'platform_post_id' => $mediaId,
            'platform_post_url' => $permalink,
        ];
    }

    private function fetchPermalink(string $mediaId, string $pageToken): ?string
    {
        $response = Http::get(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$mediaId,
            ['fields' => 'permalink', 'access_token' => $pageToken],
        );

        if (! $response->ok()) {
            return null;
        }

        $link = $response->json('permalink');

        return is_string($link) && $link !== '' ? $link : null;
    }
}
