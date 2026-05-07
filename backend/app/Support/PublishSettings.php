<?php

namespace App\Support;

class PublishSettings
{
    public const STYLES = [
        'waterfall',
        'grid',
        'grid_carousel',
        'carousel',
        'showcase_carousel',
        'mosaic',
        'tetris',
        'select',
        'cover_flow',
        'list',
        'stagger',
        'layers',
    ];

    public static function defaults(): array
    {
        return [
            'feed_style' => 'grid',
            'feed' => [
                'lazy_load' => true,
                'posts_per_page' => 12,
                'post_min_width' => 260,
                'show_load_more' => true,
            ],
            'post' => [
                'show_titles' => true,
                'show_share_icons' => false,
                'show_comments' => false,
                'show_likes' => false,
                'autoplay_videos' => false,
                'show_platform_icon' => true,
                'show_feed_name' => true,
                'source_row_layout' => 'stacked',
                'source_row_alignment' => 'center',
                'showcase_content_alignment' => 'start',
                'showcase_share_icon' => 'upload_share',
                'showcase_share_icon_color_mode' => 'post_icon',
                'showcase_share_icon_color' => '#e2e8f0',
            ],
            'colors' => [
                'post_icon' => '#64748b',
                'post_text' => '#0f172a',
                'post_date' => '#64748b',
                'post_link' => '#2563eb',
                'post_button' => '#0f172a',
                'post_border' => [
                    'enabled' => true,
                    'color' => '#e2e8f0',
                ],
                'post_bg' => [
                    'enabled' => true,
                    'color' => '#ffffff',
                ],
            ],
            'branding' => [
                'media_badge' => [
                    'show' => true,
                    'image_source' => 'platform',
                    'custom_url' => '',
                    'position' => 'center',
                ],
                'source_icon' => [
                    'show' => true,
                    'image_source' => 'platform',
                    'custom_url' => '',
                    'position' => 'before_name',
                ],
                'account_avatar' => [
                    'show' => true,
                    'image_source' => 'connected',
                    'custom_url' => '',
                    'position' => 'footer_start',
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>|null  $stored */
    public static function merge(?array $stored): array
    {
        $base = self::defaults();
        if (! is_array($stored) || $stored === []) {
            return $base;
        }

        return array_replace_recursive($base, $stored);
    }

    /** @return array<string, mixed> */
    public static function validateAndNormalize(array $tree): array
    {
        $defaults = self::defaults();
        $out = array_replace_recursive($defaults, $tree);

        if (! in_array($out['feed_style'], self::STYLES, true)) {
            $out['feed_style'] = $defaults['feed_style'];
        }

        $out['feed']['lazy_load'] = (bool) ($out['feed']['lazy_load'] ?? true);
        $out['feed']['show_load_more'] = (bool) ($out['feed']['show_load_more'] ?? true);
        $perPage = (int) ($out['feed']['posts_per_page'] ?? 12);
        $out['feed']['posts_per_page'] = max(1, min($perPage, 100));
        $minW = (int) ($out['feed']['post_min_width'] ?? 260);
        $out['feed']['post_min_width'] = max(120, min($minW, 600));

        $out['post']['show_titles'] = (bool) ($out['post']['show_titles'] ?? true);
        $out['post']['show_share_icons'] = (bool) ($out['post']['show_share_icons'] ?? false);
        $out['post']['show_comments'] = (bool) ($out['post']['show_comments'] ?? false);
        $out['post']['show_likes'] = (bool) ($out['post']['show_likes'] ?? false);
        $out['post']['autoplay_videos'] = (bool) ($out['post']['autoplay_videos'] ?? false);
        $out['post']['show_platform_icon'] = (bool) ($out['post']['show_platform_icon'] ?? true);
        $out['post']['show_feed_name'] = (bool) ($out['post']['show_feed_name'] ?? true);
        $out['post']['source_row_layout'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($out['post']['source_row_layout'] ?? 'stacked')),
            ['stacked', 'inline'],
            'stacked',
        );
        $out['post']['source_row_alignment'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($out['post']['source_row_alignment'] ?? 'center')),
            ['start', 'center'],
            'center',
        );
        $out['post']['showcase_content_alignment'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($out['post']['showcase_content_alignment'] ?? 'start')),
            ['start', 'center'],
            'start',
        );
        $out['post']['showcase_share_icon'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($out['post']['showcase_share_icon'] ?? 'upload_share')),
            ['upload_share', 'arrow', 'none'],
            'upload_share',
        );
        $out['post']['showcase_share_icon_color_mode'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($out['post']['showcase_share_icon_color_mode'] ?? 'post_icon')),
            ['post_icon', 'post_text', 'post_button', 'custom'],
            'post_icon',
        );
        $out['post']['showcase_share_icon_color'] = self::sanitizeHexColor(
            (string) ($out['post']['showcase_share_icon_color'] ?? '#e2e8f0'),
            '#e2e8f0',
        );

        foreach (['post_icon', 'post_text', 'post_date', 'post_link', 'post_button'] as $key) {
            $out['colors'][$key] = self::sanitizeHexColor((string) ($out['colors'][$key] ?? $defaults['colors'][$key]), (string) $defaults['colors'][$key]);
        }

        $out['colors']['post_border']['enabled'] = (bool) ($out['colors']['post_border']['enabled'] ?? true);
        $out['colors']['post_border']['color'] = self::sanitizeHexColor(
            (string) ($out['colors']['post_border']['color'] ?? $defaults['colors']['post_border']['color']),
            (string) $defaults['colors']['post_border']['color'],
        );
        $out['colors']['post_bg']['enabled'] = (bool) ($out['colors']['post_bg']['enabled'] ?? true);
        $out['colors']['post_bg']['color'] = self::sanitizeHexColor(
            (string) ($out['colors']['post_bg']['color'] ?? $defaults['colors']['post_bg']['color']),
            (string) $defaults['colors']['post_bg']['color'],
        );

        $out['branding'] = self::normalizeBranding($out['branding'] ?? [], $defaults['branding']);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $b
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private static function normalizeBranding(array $b, array $defaults): array
    {
        $media = array_replace_recursive($defaults['media_badge'], $b['media_badge'] ?? []);
        $media['show'] = (bool) ($media['show'] ?? true);
        $media['image_source'] = self::enumOrFallback(
            (string) ($media['image_source'] ?? 'platform'),
            ['platform', 'custom', 'none'],
            'platform',
        );
        $media['custom_url'] = self::sanitizeOptionalHttpUrl((string) ($media['custom_url'] ?? ''));
        $media['position'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($media['position'] ?? 'center')),
            ['center', 'top_left', 'top_right', 'bottom_left', 'bottom_right'],
            'center',
        );

        $src = array_replace_recursive($defaults['source_icon'], $b['source_icon'] ?? []);
        $src['show'] = (bool) ($src['show'] ?? true);
        $src['image_source'] = self::enumOrFallback(
            (string) ($src['image_source'] ?? 'platform'),
            ['platform', 'custom', 'none'],
            'platform',
        );
        $src['custom_url'] = self::sanitizeOptionalHttpUrl((string) ($src['custom_url'] ?? ''));
        $src['position'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($src['position'] ?? 'before_name')),
            ['before_name', 'after_name'],
            'before_name',
        );

        $acct = array_replace_recursive($defaults['account_avatar'], $b['account_avatar'] ?? []);
        $acct['show'] = (bool) ($acct['show'] ?? true);
        $acct['image_source'] = self::enumOrFallback(
            (string) ($acct['image_source'] ?? 'connected'),
            ['connected', 'initial', 'custom', 'none'],
            'connected',
        );
        $acct['custom_url'] = self::sanitizeOptionalHttpUrl((string) ($acct['custom_url'] ?? ''));
        $acct['position'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($acct['position'] ?? 'footer_start')),
            ['footer_start', 'footer_end'],
            'footer_start',
        );

        return [
            'media_badge' => $media,
            'source_icon' => $src,
            'account_avatar' => $acct,
        ];
    }

    /** @param  list<string>  $allowed */
    private static function enumOrFallback(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function sanitizeOptionalHttpUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 2048) {
            return '';
        }
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $value : '';
    }

    private static function sanitizeHexColor(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            if (strlen($value) === 4) {
                return '#'.$value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
            }

            return strtolower($value);
        }

        return strtolower($fallback);
    }
}
