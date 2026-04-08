<?php

namespace App\Support;

class PublishSettings
{
    public const STYLES = [
        'waterfall',
        'grid',
        'grid_carousel',
        'carousel',
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

        return $out;
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
