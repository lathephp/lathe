<?php

namespace Lathe\Theme;

/**
 * The WCAG 2.x contrast ratio of two CSS colours written as hex, rgb(), hsl() or oklch().
 * scripts/lib/colour.mjs does the same maths for the build check. Alpha is ignored,
 * because a translucent colour has no fixed backdrop.
 */
class Contrast
{
    /**
     * The ratio from 1 to 21, or null when either colour is not in a notation above.
     */
    public static function ratio(string $first, string $second): ?float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        if ($a === null || $b === null) {
            return null;
        }

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * One sentence for each pair of theme.colors whose two tokens are both overridden and
     * too faint in a mode: a foreground on its surface (4.5:1), ring and chart-1 to 5 on
     * background and card (3:1). A token left at null keeps the shipped value, which the
     * build check already covers, so a pair with a null side is not measured.
     *
     * @param  array<array-key, mixed>  $colors  token => colour, ['light' => ..., 'dark' => ...] or null
     * @return list<string>
     */
    public static function weakPairs(array $colors): array
    {
        $pairs = [];

        foreach (Color::cases() as $color) {
            $name = $color->value;

            if (str_ends_with($name, '-foreground')) {
                $pairs[] = [$name, substr($name, 0, -11), 4.5];
            }
        }

        foreach (['ring', 'chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5'] as $name) {
            $pairs[] = [$name, 'background', 3.0];
            $pairs[] = [$name, 'card', 3.0];
        }

        $weak = [];

        foreach ($pairs as [$front, $back, $min]) {
            foreach (['light', 'dark'] as $mode) {
                $a = self::override($colors[$front] ?? null, $mode);
                $b = self::override($colors[$back] ?? null, $mode);
                $ratio = $a !== null && $b !== null ? self::ratio($a, $b) : null;

                if ($ratio !== null && $ratio < $min) {
                    $weak[] = sprintf('theme.colors.%s on %s is %.2f:1 in %s mode, under %s:1. Pick colours that differ more.', $front, $back, $ratio, $mode, rtrim(rtrim(number_format($min, 1), '0'), '.'));
                }
            }
        }

        return $weak;
    }

    /**
     * The colour moved in oklch lightness until it reads at 4.5:1 on its tint, the
     * color-mix(in oklch, <colour> 14%, <ground>) of a -subtle token. It darkens on a
     * light ground and lightens on a dark one. Null when either colour is not in a
     * notation above.
     *
     * @internal
     */
    public static function subtleForeground(string $color, string $ground): ?string
    {
        $front = self::oklch(strtolower(trim($color)));
        $back = self::oklch(strtolower(trim($ground)));

        if ($front === null || $back === null) {
            return null;
        }

        // color-mix() takes the hue of the other colour when one has no chroma, else the shorter way round.
        $hue = $back[1] < 0.0001 ? $front[2] : ($front[1] < 0.0001 ? $back[2] : $front[2] + 0.86 * (fmod($back[2] - $front[2] + 540, 360) - 180));
        $tint = sprintf('oklch(%.4f %.4f %.2f)', 0.14 * $front[0] + 0.86 * $back[0], 0.14 * $front[1] + 0.86 * $back[1], fmod($hue + 360, 360));
        $step = $back[0] > 0.5 ? -0.01 : 0.01;

        for ($i = 0; $i <= 100; $i++) {
            $lightness = min(1.0, max(0.0, $front[0] + $i * $step));
            $candidate = sprintf('oklch(%.3f %.3f %.2f)', $lightness, $front[1], $front[2]);

            if (self::ratio($candidate, $tint) >= 4.5 || $lightness <= 0.0 || $lightness >= 1.0) {
                return $candidate;
            }
        }

        return null;
    }

    private static function override(mixed $value, string $mode): ?string
    {
        $colour = is_array($value) ? ($value[$mode] ?? null) : $value;

        return is_string($colour) ? $colour : null;
    }

    private static function luminance(string $color): ?float
    {
        $rgb = self::linear(strtolower(trim($color)));

        return $rgb === null ? null : 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }

    /**
     * @return array{float, float, float}|null linear sRGB, each channel from 0 to 1
     */
    private static function linear(string $v): ?array
    {
        if (preg_match('/^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?(?:\s*\/.*)?\)$/', $v, $m)) {
            $lightness = $m[2] === '%' ? (float) $m[1] / 100 : (float) $m[1];
            $a = (float) $m[3] * cos(deg2rad((float) $m[4]));
            $b = (float) $m[3] * sin(deg2rad((float) $m[4]));
            $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
            $mid = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
            $s = ($lightness - 0.0894841775 * $a - 1.291485548 * $b) ** 3;

            return [
                self::clamp(4.0767416621 * $l - 3.3077115913 * $mid + 0.2309699292 * $s),
                self::clamp(-1.2684380046 * $l + 2.6097574011 * $mid - 0.3413193965 * $s),
                self::clamp(-0.0041960863 * $l - 0.7034186147 * $mid + 1.707614701 * $s),
            ];
        }

        if (preg_match('/^#([0-9a-f]{3,8})$/', $v, $m)) {
            $hex = strlen($m[1]) < 6 ? implode('', array_map(fn (string $c) => $c.$c, str_split($m[1]))) : $m[1];

            return [
                self::gamma(hexdec(substr($hex, 0, 2)) / 255),
                self::gamma(hexdec(substr($hex, 2, 2)) / 255),
                self::gamma(hexdec(substr($hex, 4, 2)) / 255),
            ];
        }

        if (preg_match('/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/', $v, $m)) {
            return [
                self::gamma(self::clamp((float) $m[1] / 255)),
                self::gamma(self::clamp((float) $m[2] / 255)),
                self::gamma(self::clamp((float) $m[3] / 255)),
            ];
        }

        if (preg_match('/^hsla?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%/', $v, $m)) {
            $hue = (float) $m[1];
            $sat = (float) $m[2] / 100;
            $light = (float) $m[3] / 100;
            $channel = function (int $n) use ($hue, $sat, $light): float {
                $k = fmod($n + $hue / 30, 12);

                return self::gamma($light - $sat * min($light, 1 - $light) * max(-1, min($k - 3, 9 - $k, 1)));
            };

            return [$channel(0), $channel(8), $channel(4)];
        }

        return null;
    }

    /**
     * @return array{float, float, float}|null oklch lightness, chroma and hue in degrees
     */
    private static function oklch(string $v): ?array
    {
        // An oklch() colour is read as written, so a chroma outside sRGB is not clipped first.
        if (preg_match('/^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?(?:\s*\/.*)?\)$/', $v, $m)) {
            return [$m[2] === '%' ? (float) $m[1] / 100 : (float) $m[1], (float) $m[3], (float) $m[4]];
        }

        $rgb = self::linear($v);

        if ($rgb === null) {
            return null;
        }

        [$r, $g, $b] = $rgb;
        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $mid = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);
        $a = 1.9779984951 * $l - 2.428592205 * $mid + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $mid - 0.808675766 * $s;

        return [
            0.2104542553 * $l + 0.793617785 * $mid - 0.0040720468 * $s,
            sqrt($a ** 2 + $bb ** 2),
            fmod(rad2deg(atan2($bb, $a)) + 360, 360),
        ];
    }

    private static function gamma(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function clamp(float $n): float
    {
        return min(1.0, max(0.0, $n));
    }
}
