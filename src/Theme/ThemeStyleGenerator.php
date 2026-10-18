<?php

namespace Lathe\Theme;

/**
 * The CSS of theme.radius and theme.colors: a :root block for the light values and a
 * .dark block for the dark half of each {light, dark} pair. A single colour string
 * sits in :root only, and wins in dark mode too because the tag follows the stylesheets.
 */
class ThemeStyleGenerator
{
    /** The CSS colour keywords, lowercase. */
    protected const array KEYWORDS = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue',
        'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson',
        'currentcolor', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta',
        'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise',
        'darkviolet', 'deeppink', 'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia',
        'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink',
        'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral',
        'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray',
        'lightslategrey', 'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue',
        'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose',
        'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod',
        'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple',
        'rebeccapurple', 'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna',
        'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan', 'teal',
        'thistle', 'tomato', 'transparent', 'turquoise', 'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen',
    ];

    /**
     * @param  array<array-key, mixed>  $colors  token => colour, ['light' => ..., 'dark' => ...] or null
     */
    public function generate(string $radius, array $colors): string
    {
        // The values land inside a <style> tag, so anything but a colour or a length is dropped.
        $light = static::isLength($radius) ? ["--radius:{$radius}"] : [];
        $dark = [];

        $set = [];

        foreach (Color::cases() as $color) {
            $value = $colors[$color->value] ?? null;

            if (is_string($value) && static::isColor($value)) {
                $set[$color->value] = [$value, null];
            } elseif (is_array($value) && is_string($value['light'] ?? null) && is_string($value['dark'] ?? null)
                && static::isColor($value['light']) && static::isColor($value['dark'])) {
                $set[$color->value] = [$value['light'], $value['dark']];
            }
        }

        $tokens = $set + $this->derive($set);

        foreach (Color::cases() as $color) {
            if (isset($tokens[$color->value])) {
                [$lightValue, $darkValue] = $tokens[$color->value];
                $light[] = "{$color->variable()}:{$lightValue}";

                if ($darkValue !== null) {
                    $dark[] = "{$color->variable()}:{$darkValue}";
                }
            }
        }

        return ':root{'.implode(';', $light).'}'.($dark === [] ? '' : '.dark{'.implode(';', $dark).'}');
    }

    /**
     * The tokens that follow the app's own colours, so an app with theme.colors does not
     * wear copper in them. Each one derives only from a token the app set, so an app with
     * no colours gets the copper values of app.css. A derived value is not checked by
     * isColor(), because color-mix() fails it, but it only embeds colours that passed.
     *
     * @param  array<string, array{string, ?string}>  $set  token => [light or only value, dark or null]
     * @return array<string, array{string, ?string}>
     */
    private function derive(array $set): array
    {
        $copy = fn (string $from, string ...$to): array => isset($set[$from]) ? array_fill_keys($to, $set[$from]) : [];
        $derived = [
            ...$copy('primary', 'brand', 'focus'),
            ...$copy('muted', 'skeleton'),
            ...$copy('muted-foreground', 'chart-other'),
            ...$copy(isset($set['sidebar-accent']) ? 'sidebar-accent' : 'accent', 'sidebar-hover'),
        ];

        if (isset($set['primary']) && ! isset($set['link'])) {
            $derived['link'] = $set['primary'];
            $derived['link-hover'] = $this->map($set['primary'], fn (string $c): string => "color-mix(in oklch, {$c} 85%, black)");
        }

        foreach (['primary', 'success', 'warning', 'info', 'destructive'] as $name) {
            if (isset($set[$name])) {
                $derived["{$name}-subtle"] = $this->map($set[$name], fn (string $c): string => "color-mix(in oklch, {$c} 14%, var(--background))");
                $derived["{$name}-subtle-foreground"] = $this->map($set[$name], fn (string $c, string $mode): string => $this->subtleForeground($c, $mode, $set['background'] ?? null));
            }
        }

        return $derived;
    }

    /**
     * @param  array{string, ?string}  $pair
     * @param  callable(string, string): string  $make  colour, light or dark => derived colour
     * @return array{string, ?string}
     */
    private function map(array $pair, callable $make): array
    {
        return [$make($pair[0], 'light'), $pair[1] === null ? null : $make($pair[1], 'dark')];
    }

    /**
     * The colour at 4.5:1 on its -subtle tint, worked out here because CSS cannot search
     * for a contrast. A single-string colour sits in :root, so it is measured on the light
     * ground. Without an app background the ground is copper's, from resources/css/app.css.
     *
     * @param  array{string, ?string}|null  $background
     */
    private function subtleForeground(string $color, string $mode, ?array $background): string
    {
        $ground = $background === null
            ? ($mode === 'light' ? 'oklch(0.985 0.003 70)' : 'oklch(0.165 0.006 60)')
            : ($mode === 'light' ? $background[0] : $background[1] ?? $background[0]);

        // A keyword such as red has no parsed value, so it is mixed toward black or white instead.
        return Contrast::subtleForeground($color, $ground)
            ?? "color-mix(in oklch, {$color} 60%, ".($mode === 'light' ? 'black' : 'white').')';
    }

    /**
     * Whether the value is an oklch(), hsl(), hsla(), rgb() or rgba() colour, a hex colour or a colour keyword.
     */
    public static function isColor(string $value): bool
    {
        return preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/iD', $value) === 1
            || preg_match('/^(oklch|hsla?|rgba?)\([0-9a-z.% ,\/+-]*\)$/iD', $value) === 1
            || in_array(strtolower($value), self::KEYWORDS, true);
    }

    /**
     * Whether the value is a CSS length such as 0.625rem, or a bare 0.
     */
    public static function isLength(string $value): bool
    {
        return preg_match('/^(0|\d*\.?\d+(px|rem|em|ch|ex|vw|vh|vmin|vmax|pt|pc|cm|mm|in|q))$/iD', $value) === 1;
    }
}
