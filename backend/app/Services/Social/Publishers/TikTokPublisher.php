<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use App\Services\Social\Support\ContentPackageCaptionBuilder;
use App\Services\Social\Support\TikTokContentPostingClient;
use RuntimeException;

class TikTokPublisher implements PublisherInterface
{
    private const MAX_TITLE_LENGTH = 2200;

    private const PRIVACY_PREFERENCE = [
        'PUBLIC_TO_EVERYONE',
        'MUTUAL_FOLLOW_FRIENDS',
        'FOLLOWER_OF_CREATOR',
        'SELF_ONLY',
    ];

    public function __construct(
        private readonly TikTokContentPostingClient $client = new TikTokContentPostingClient,
    ) {}

    public function publish(ScheduledPost $scheduledPost): array
    {
        $scheduledPost->loadMissing(['socialCredential', 'contentPackage']);

        $credential = $scheduledPost->socialCredential;
        if (! $credential || $credential->provider !== 'tiktok') {
            throw new RuntimeException('A connected TikTok credential is required.');
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            throw new RuntimeException('TikTok token has expired. Reconnect the account in Credentials.');
        }

        $package = $scheduledPost->contentPackage;
        if (! $package) {
            throw new RuntimeException('Schedule a content package for TikTok publishing.');
        }

        $videoUrl = ContentPackageCaptionBuilder::firstMediaUrl($package);
        if (! $videoUrl || ! $this->isHttpsUrl($videoUrl)) {
            throw new RuntimeException('TikTok requires a public HTTPS video URL in content package media_urls. Verify the URL domain in the TikTok developer portal for PULL_FROM_URL.');
        }

        $title = ContentPackageCaptionBuilder::build($package, self::MAX_TITLE_LENGTH);
        if ($title === '') {
            throw new RuntimeException('Cannot publish without a caption/title on the content package.');
        }

        $tiktokOptions = $this->tiktokPackageOptions($package->platform_specific_data);

        $creator = $this->client->queryCreatorInfo($token);
        $privacyLevel = $this->resolvePrivacyLevel(
            $creator['privacy_level_options'] ?? [],
            $tiktokOptions['privacy_level'] ?? null,
        );

        $init = $this->client->initVideoPullFromUrl($token, $videoUrl, [
            'title' => $title,
            'privacy_level' => $privacyLevel,
            'disable_duet' => false,
            'disable_comment' => false,
            'disable_stitch' => false,
            'brand_content_toggle' => (bool) ($tiktokOptions['brand_content_toggle'] ?? false),
            'brand_organic_toggle' => (bool) ($tiktokOptions['brand_organic_toggle'] ?? false),
        ]);

        $publishId = $init['publish_id'];
        $status = $this->client->waitUntilComplete($token, $publishId);

        $postIds = $status['publicaly_available_post_id'] ?? [];
        $platformPostId = is_array($postIds) && isset($postIds[0])
            ? (string) $postIds[0]
            : $publishId;

        $username = (string) ($creator['creator_username'] ?? '');
        $platformPostUrl = $this->buildPostUrl($username, $platformPostId, $publishId);

        return [
            'platform_post_id' => $platformPostId,
            'platform_post_url' => $platformPostUrl,
        ];
    }

    /** @return array<string, mixed> */
    private function tiktokPackageOptions(mixed $platformSpecificData): array
    {
        if (! is_array($platformSpecificData)) {
            return [];
        }

        $tiktok = $platformSpecificData['tiktok'] ?? [];

        return is_array($tiktok) ? $tiktok : [];
    }

    /** @param  list<string>|mixed  $options */
    private function resolvePrivacyLevel(mixed $options, ?string $requested): string
    {
        $options = is_array($options)
            ? array_values(array_filter($options, static fn ($v) => is_string($v) && $v !== ''))
            : [];

        if ($requested && in_array($requested, $options, true)) {
            return $requested;
        }

        foreach (self::PRIVACY_PREFERENCE as $pref) {
            if (in_array($pref, $options, true)) {
                return $pref;
            }
        }

        if ($options !== []) {
            return (string) $options[0];
        }

        return 'SELF_ONLY';
    }

    private function buildPostUrl(string $username, string $platformPostId, string $publishId): ?string
    {
        if ($username !== '' && $platformPostId !== '' && $platformPostId !== $publishId) {
            return 'https://www.tiktok.com/@'.ltrim($username, '@').'/video/'.$platformPostId;
        }

        return null;
    }

    private function isHttpsUrl(string $url): bool
    {
        return (bool) preg_match('#^https://#i', $url);
    }
}
