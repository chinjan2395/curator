<?php

namespace App\Support;

use App\Support\Concerns\NormalizesSettings;

/**
 * Settings authority for Brand Kit's expanded shape: the kit's own
 * identity groups (colors/fonts/watermark, consumed by AI generation)
 * plus every Publish-page customization group (feed/post/feed_colors/
 * widget/branding/feed_style), so a Brand Kit can carry and inherit
 * everything a Workspace's `publish_settings` can.
 *
 * Replaces `BrandKitSettings` as the source of truth for Brand Kit
 * defaults/normalization. See `mapToPublishSettings()` for how a
 * resolved tree here translates into `PublishSettings`' shape.
 */
class BrandKitExpandedSettings
{
    use NormalizesSettings;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $publishDefaults = PublishSettings::defaults();

        return [
            'colors' => [
                'primary' => '#2563eb',
                'secondary' => '#64748b',
                'accent' => '#0f172a',
                'background' => '#ffffff',
                'text' => '#0f172a',
            ],
            'fonts' => [
                'heading' => 'inherit',
                'body' => 'inherit',
            ],
            'watermark' => [
                'enabled' => false,
                'url' => '',
                'position' => 'bottom_right',
                'opacity' => 0.3,
            ],
            'feed_style' => $publishDefaults['feed_style'],
            'feed' => $publishDefaults['feed'],
            'post' => $publishDefaults['post'],
            'feed_colors' => $publishDefaults['colors'],
            'widget' => $publishDefaults['widget'],
            'branding' => $publishDefaults['branding'],
        ];
    }

    /** @param  array<string, mixed>|null  $overrides */
    public static function merge(?array $overrides): array
    {
        $base = self::defaults();
        if (! is_array($overrides) || $overrides === []) {
            return $base;
        }

        return self::deepMerge($base, $overrides);
    }

    /** @return array<string, mixed> */
    public static function validateAndNormalize(array $settings): array
    {
        $defaults = self::defaults();
        $out = self::deepMerge($defaults, $settings);

        // --- Brand-kit-native groups (colors/fonts/watermark) ---
        $outColors = [];
        foreach (array_keys($defaults['colors']) as $key) {
            $outColors[$key] = self::sanitizeHexColor(
                (string) ($out['colors'][$key] ?? $defaults['colors'][$key]),
                (string) $defaults['colors'][$key],
            );
        }
        $out['colors'] = $outColors;

        $outFonts = [];
        foreach (array_keys($defaults['fonts']) as $key) {
            $outFonts[$key] = self::sanitizeFontFamily(
                (string) ($out['fonts'][$key] ?? $defaults['fonts'][$key]),
                (string) $defaults['fonts'][$key],
            );
        }
        $out['fonts'] = $outFonts;

        $wm = $out['watermark'];
        $wm['enabled'] = (bool) ($wm['enabled'] ?? false);
        $wm['url'] = self::sanitizeOptionalHttpUrl((string) ($wm['url'] ?? ''));
        $wm['position'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($wm['position'] ?? 'bottom_right')),
            ['top_left', 'top_right', 'bottom_left', 'bottom_right', 'center'],
            'bottom_right',
        );
        $opacity = (float) ($wm['opacity'] ?? 0.3);
        $wm['opacity'] = max(0.0, min($opacity, 1.0));
        $out['watermark'] = $wm;

        // --- Publish-page-shaped groups, delegated to PublishSettings' rules ---
        // `PublishSettings::validateAndNormalize()` expects `feed_style`/`feed`/
        // `post`/`colors`/`widget`/`branding` at the top level, so we borrow its
        // shape for the shared groups, run it, then fold the result back in
        // under this class's own naming (`feed_colors` instead of `colors`).
        $publishShaped = PublishSettings::validateAndNormalize([
            'feed_style' => $out['feed_style'],
            'feed' => $out['feed'],
            'post' => $out['post'],
            'colors' => $out['feed_colors'],
            'widget' => $out['widget'],
            'branding' => $out['branding'],
        ]);

        $out['feed_style'] = $publishShaped['feed_style'];
        $out['feed'] = $publishShaped['feed'];
        $out['post'] = $publishShaped['post'];
        $out['feed_colors'] = $publishShaped['colors'];
        $out['widget'] = $publishShaped['widget'];
        $out['branding'] = $publishShaped['branding'];

        return $out;
    }

    public static function sanitizeLogoUrl(?string $value): ?string
    {
        $url = self::sanitizeOptionalHttpUrl((string) ($value ?? ''));

        return $url !== '' ? $url : null;
    }

    /**
     * Translates a resolved brand-kit tree (as returned by `BrandKit::resolve()`)
     * into the shape `PublishSettings` expects, for applying a kit to a
     * Workspace's `publish_settings`. The kit's own `colors`/`fonts`/`watermark`
     * groups are brand-kit-native (consumed by AI generation, not the embed
     * widget) and are intentionally dropped here.
     *
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    public static function mapToPublishSettings(array $resolved): array
    {
        $defaults = self::defaults();

        return [
            'feed_style' => $resolved['feed_style'] ?? $defaults['feed_style'],
            'feed' => $resolved['feed'] ?? $defaults['feed'],
            'post' => $resolved['post'] ?? $defaults['post'],
            'colors' => $resolved['feed_colors'] ?? $defaults['feed_colors'],
            'widget' => $resolved['widget'] ?? $defaults['widget'],
            'branding' => $resolved['branding'] ?? $defaults['branding'],
        ];
    }

    private static function sanitizeFontFamily(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, 120);
    }
}
