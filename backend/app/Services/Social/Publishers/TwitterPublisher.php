<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwitterPublisher implements PublisherInterface
{
    private const X_API_BASE = 'https://api.x.com/2';

    private const MAX_TWEET_LENGTH = 280;

    public function __construct(
        private readonly TwitterMediaUploader $mediaUploader = new TwitterMediaUploader,
    ) {}

    public function publish(ScheduledPost $scheduledPost): array
    {
        $scheduledPost->loadMissing(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        if (! $credential || $credential->provider !== 'twitter') {
            throw new RuntimeException('A connected X / Twitter credential is required.');
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            throw new RuntimeException('X / Twitter token has expired. Reconnect the account in Credentials.');
        }

        $package = $scheduledPost->contentPackage;
        if (! $package) {
            throw new RuntimeException('Schedule a content package with caption text for X / Twitter publishing.');
        }

        $text = ContentPackageCaptionBuilder::build($package, self::MAX_TWEET_LENGTH);
        if ($text === '') {
            throw new RuntimeException('Cannot publish an empty tweet. Attach a content package with a caption.');
        }

        $payload = ['text' => $text];

        $mediaUrl = ContentPackageCaptionBuilder::firstMediaUrl($package);
        if ($mediaUrl !== null) {
            $mediaId = $this->mediaUploader->uploadFromUrl($token, $mediaUrl);
            $payload['media'] = ['media_ids' => [$mediaId]];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->post(self::X_API_BASE.'/tweets', $payload);

        if (! $response->ok()) {
            $detail = $response->json('detail')
                ?? $response->json('title')
                ?? $response->json('errors.0.message')
                ?? $response->body();

            throw new RuntimeException('X API publish failed: '.$detail);
        }

        $tweetId = (string) ($response->json('data.id') ?? '');
        if ($tweetId === '') {
            throw new RuntimeException('X API returned no tweet id.');
        }

        return [
            'platform_post_id' => $tweetId,
            'platform_post_url' => 'https://x.com/i/web/status/'.$tweetId,
        ];
    }
}
