<?php

namespace App\Support\Concerns;

trait NormalizesSettings
{
    /**
     * Deep-merges two settings trees, but only recurses when both sides are
     * associative arrays for a given key. List arrays (or non-array values)
     * in the patch fully replace the base value instead of being merged by
     * numeric index (unlike array_replace_recursive).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public static function deepMerge(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($value)) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /** @param  list<string>  $allowed */
    protected static function enumOrFallback(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    protected static function sanitizeOptionalHttpUrl(string $value): string
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

    protected static function sanitizeHexColor(string $value, string $fallback): string
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
