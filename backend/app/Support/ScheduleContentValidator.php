<?php

namespace App\Support;

use App\Models\ContentPackage;
use App\Services\Social\SocialPublisherService;
use App\Services\Social\Support\MediaUrlClassifier;

class ScheduleContentValidator
{
    /**
     * @return array{valid: bool, checks: list<array{id: string, label: string, passed: bool, message: string|null}>}
     */
    public static function validate(ContentPackage $package, string $provider): array
    {
        $provider = PlatformPublishSpecs::normalize($provider);
        $checks = [];

        $caption = trim((string) $package->caption);
        $checks[] = self::check(
            'caption',
            'Caption text',
            $caption !== '',
            $caption !== '' ? null : 'Add caption text to the content package before scheduling.',
        );

        $packagePlatform = PlatformPublishSpecs::normalize((string) $package->platform);
        $checks[] = self::check(
            'platform_match',
            'Platform matches account',
            $packagePlatform === $provider,
            $packagePlatform === $provider
                ? null
                : "This package is for {$packagePlatform} but you selected {$provider}. Pick a matching package or account.",
        );

        $mediaUrls = self::mediaUrls($package);
        $hasMedia = $mediaUrls !== [];

        if (in_array($provider, ['instagram', 'tiktok'], true)) {
            $checks[] = self::check(
                'media_required',
                'Media attached',
                $hasMedia,
                $hasMedia
                    ? null
                    : ucfirst($provider).' requires at least one image or video URL on the content package (text-only posts are not supported).',
            );
        }

        if ($hasMedia && in_array($provider, ['instagram', 'tiktok', 'threads'], true)) {
            $allHttps = self::allHttps($mediaUrls);
            $checks[] = self::check(
                'media_https',
                'Public HTTPS media URLs',
                $allHttps,
                $allHttps
                    ? null
                    : 'All media URLs must start with https:// so the platform can fetch them (localhost links will fail).',
            );
        }

        if ($hasMedia && in_array($provider, ['twitter', 'linkedin'], true)) {
            $hasVideo = self::containsVideo($mediaUrls);
            $checks[] = self::check(
                'no_video',
                'No video URLs',
                ! $hasVideo,
                $hasVideo
                    ? ucfirst($provider).' native publish does not support video yet. Remove video URLs or use images/text only.'
                    : null,
            );
        }

        if ($provider === 'instagram' && $hasMedia) {
            $onlyImagesForCarousel = count($mediaUrls) < 2 || ! self::containsVideo($mediaUrls);
            if (count($mediaUrls) >= 2) {
                $checks[] = self::check(
                    'carousel_images',
                    'Carousel uses images only',
                    $onlyImagesForCarousel,
                    $onlyImagesForCarousel
                        ? null
                        : 'Instagram carousels support images only. Use a single video URL for a Reel.',
                );
            }
        }

        if ($provider === 'tiktok' && $hasMedia && count($mediaUrls) > 1) {
            $allImages = ! self::containsVideo($mediaUrls);
            $checks[] = self::check(
                'tiktok_single_video',
                'TikTok video posts use one URL',
                $allImages || count($mediaUrls) === 1,
                (count($mediaUrls) === 1 || $allImages)
                    ? null
                    : 'TikTok allows one video URL per post. Use multiple image URLs for a photo post.',
            );
        }

        if (! in_array($provider, SocialPublisherService::NATIVE_PUBLISH_PROVIDERS, true)) {
            $checks[] = self::check(
                'native_supported',
                'Native publish supported',
                false,
                ucfirst($provider).' cannot be scheduled for native publish. Use embed publishing instead.',
            );
        }

        $valid = collect($checks)->every(static fn (array $c) => $c['passed']);

        return ['valid' => $valid, 'checks' => $checks];
    }

    /** @return list<string> */
    private static function mediaUrls(ContentPackage $package): array
    {
        $urls = $package->media_urls ?? [];

        return is_array($urls)
            ? array_values(array_filter($urls, static fn ($u) => is_string($u) && $u !== ''))
            : [];
    }

    /** @param  list<string>  $urls */
    private static function allHttps(array $urls): bool
    {
        foreach ($urls as $url) {
            if (! MediaUrlClassifier::isHttpsUrl($url)) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<string>  $urls */
    private static function containsVideo(array $urls): bool
    {
        foreach ($urls as $url) {
            if (MediaUrlClassifier::isVideo($url)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{id: string, label: string, passed: bool, message: string|null} */
    private static function check(string $id, string $label, bool $passed, ?string $message): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'passed' => $passed,
            'message' => $passed ? null : $message,
        ];
    }
}
