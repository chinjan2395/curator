<?php

namespace App\Support;

class BrandKitSettings
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
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
        ];
    }

    /**
     * @param  array<string, mixed>|null  $colors
     * @param  array<string, mixed>|null  $fonts
     * @param  array<string, mixed>|null  $watermark
     * @return array{colors: array<string, string>, fonts: array<string, string>, watermark: array<string, mixed>}
     */
    public static function normalize(?array $colors, ?array $fonts, ?array $watermark): array
    {
        $defaults = self::defaults();

        $outColors = [];
        foreach (array_keys($defaults['colors']) as $key) {
            $outColors[$key] = self::sanitizeHexColor(
                (string) (($colors ?? [])[$key] ?? $defaults['colors'][$key]),
                (string) $defaults['colors'][$key],
            );
        }

        $outFonts = [];
        foreach (array_keys($defaults['fonts']) as $key) {
            $outFonts[$key] = self::sanitizeFontFamily(
                (string) (($fonts ?? [])[$key] ?? $defaults['fonts'][$key]),
                (string) $defaults['fonts'][$key],
            );
        }

        $wm = array_replace_recursive($defaults['watermark'], is_array($watermark) ? $watermark : []);
        $wm['enabled'] = (bool) ($wm['enabled'] ?? false);
        $wm['url'] = self::sanitizeOptionalHttpUrl((string) ($wm['url'] ?? ''));
        $wm['position'] = self::enumOrFallback(
            str_replace('-', '_', (string) ($wm['position'] ?? 'bottom_right')),
            ['top_left', 'top_right', 'bottom_left', 'bottom_right', 'center'],
            'bottom_right',
        );
        $opacity = (float) ($wm['opacity'] ?? 0.3);
        $wm['opacity'] = max(0.0, min($opacity, 1.0));

        return [
            'colors' => $outColors,
            'fonts' => $outFonts,
            'watermark' => $wm,
        ];
    }

    public static function sanitizeLogoUrl(?string $value): ?string
    {
        $url = self::sanitizeOptionalHttpUrl((string) ($value ?? ''));

        return $url !== '' ? $url : null;
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

    private static function sanitizeFontFamily(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, 120);
    }
}
