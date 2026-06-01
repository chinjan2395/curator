<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\FacebookGraphClient;
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

        $response = Http::asForm()->post(
            'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$resolved['page_id'].'/feed',
            [
                'message' => $message,
                'access_token' => $resolved['page_token'],
            ],
        );

        if (! $response->ok()) {
            $err = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Facebook publish failed: '.$err);
        }

        $postId = (string) ($response->json('id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('Facebook API returned no post id.');
        }

        return [
            'platform_post_id' => $postId,
            'platform_post_url' => 'https://www.facebook.com/'.$postId,
        ];
    }
}
