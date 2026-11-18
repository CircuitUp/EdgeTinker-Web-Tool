<?php

namespace App\Support\Banner;

/**
 * Colours in the banner config are stored as {r, g, b, a}: r, g and b are
 * 0–255 integers and a is 0–1 (0 = fully transparent).
 *
 * resources/js/banner/styles.js mirrors css() and num() exactly; the shared
 * fixtures in tests/fixtures/banner keep the two in step.
 */
final class Color
{
    /** Parse {r,g,b,a}, "#rgb", "#rrggbb", "#rrggbbaa" or "rgb(a)(…)". Returns null when unreadable. */
    public static function parse(mixed $value): ?array
    {
        if (is_array($value) && isset($value['r'], $value['g'], $value['b'])) {
            return self::make($value['r'], $value['g'], $value['b'], $value['a'] ?? 1);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $m)) {
            $hex = strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];

            return self::make(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)),
                strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1);
        }

        if (preg_match('/^rgba?\(\s*([\d.]+)\s*[, ]\s*([\d.]+)\s*[, ]\s*([\d.]+)\s*(?:[,\/]\s*([\d.]+)(%?)\s*)?\)$/', $value, $m)) {
            $alpha = isset($m[4]) && $m[4] !== '' ? ((float) $m[4]) / (($m[5] ?? '') === '%' ? 100 : 1) : 1;

            return self::make($m[1], $m[2], $m[3], $alpha);
        }

        return $value === 'transparent' ? self::make(0, 0, 0, 0) : null;
    }

    public static function make(mixed $r, mixed $g, mixed $b, mixed $a = 1): array
    {
        $channel = fn ($v) => max(0, min(255, (int) round((float) $v)));

        $alpha = round(max(0, min(1, (float) $a)), 3);

        return ['r' => $channel($r), 'g' => $channel($g), 'b' => $channel($b), 'a' => floor($alpha) == $alpha ? (int) $alpha : $alpha];
    }

    /** CSS value, e.g. "rgba(46,16,101,0.9)". */
    public static function css(array $color): string
    {
        return 'rgba('.$color['r'].','.$color['g'].','.$color['b'].','.self::num($color['a']).')';
    }

    /** A number with at most 3 decimals and no trailing zeros ("12.5", "0.9", "48"). */
    public static function num(float|int $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /** WCAG relative luminance of an opaque colour. */
    public static function luminance(array $color): float
    {
        $channel = function (int $v): float {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($color['r']) + 0.7152 * $channel($color['g']) + 0.0722 * $channel($color['b']);
    }

    /** $top drawn over the opaque colour $bottom. */
    public static function over(array $top, array $bottom): array
    {
        $a = $top['a'];

        return self::make($top['r'] * $a + $bottom['r'] * (1 - $a), $top['g'] * $a + $bottom['g'] * (1 - $a), $top['b'] * $a + $bottom['b'] * (1 - $a));
    }

    public static function contrast(array $a, array $b): float
    {
        [$light, $dark] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }
}
