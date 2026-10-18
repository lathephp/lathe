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
     * The defaults of the 40 tokens before the copper theme, light and dark. An app that sets
     * any colour keeps its look, so every token it left unset takes the value it had then.
     *
     * @var array<string, array{string, string}>
     */
    private const array PREVIOUS = [
        'background' => ['oklch(1 0 0)', 'oklch(0.145 0 0)'],
        'foreground' => ['oklch(0.145 0 0)', 'oklch(0.985 0 0)'],
        'card' => ['oklch(1 0 0)', 'oklch(0.205 0 0)'],
        'card-foreground' => ['oklch(0.145 0 0)', 'oklch(0.985 0 0)'],
        'popover' => ['oklch(1 0 0)', 'oklch(0.205 0 0)'],
        'popover-foreground' => ['oklch(0.145 0 0)', 'oklch(0.985 0 0)'],
        'primary' => ['oklch(0.205 0 0)', 'oklch(0.922 0 0)'],
        'primary-foreground' => ['oklch(0.985 0 0)', 'oklch(0.205 0 0)'],
        'secondary' => ['oklch(0.97 0 0)', 'oklch(0.269 0 0)'],
        'secondary-foreground' => ['oklch(0.205 0 0)', 'oklch(0.985 0 0)'],
        'muted' => ['oklch(0.97 0 0)', 'oklch(0.269 0 0)'],
        'muted-foreground' => ['oklch(0.53 0 0)', 'oklch(0.708 0 0)'],
        'accent' => ['oklch(0.97 0 0)', 'oklch(0.269 0 0)'],
        'accent-foreground' => ['oklch(0.205 0 0)', 'oklch(0.985 0 0)'],
        'destructive' => ['oklch(0.577 0.245 27.325)', 'oklch(0.704 0.191 22.216)'],
        'destructive-foreground' => ['oklch(0.985 0 0)', 'oklch(0.145 0 0)'],
        'success' => ['oklch(0.527 0.154 150.069)', 'oklch(0.723 0.219 149.579)'],
        'success-foreground' => ['oklch(0.985 0 0)', 'oklch(0.145 0 0)'],
        'warning' => ['oklch(0.555 0.163 48.998)', 'oklch(0.828 0.189 84.429)'],
        'warning-foreground' => ['oklch(0.985 0 0)', 'oklch(0.145 0 0)'],
        'info' => ['oklch(0.5 0.134 242.749)', 'oklch(0.746 0.16 232.661)'],
        'info-foreground' => ['oklch(0.985 0 0)', 'oklch(0.145 0 0)'],
        'border' => ['oklch(0.922 0 0)', 'oklch(1 0 0 / 10%)'],
        'input' => ['oklch(0.922 0 0)', 'oklch(1 0 0 / 15%)'],
        'ring' => ['oklch(0.64 0 0)', 'oklch(0.556 0 0)'],
        'link' => ['oklch(0.488 0.243 264.376)', 'oklch(0.707 0.165 254.624)'],
        'link-hover' => ['oklch(0.424 0.199 265.638)', 'oklch(0.809 0.105 251.813)'],
        'chart-1' => ['oklch(0.646 0.222 41.116)', 'oklch(0.54 0.243 264.376)'],
        'chart-2' => ['oklch(0.6 0.118 184.704)', 'oklch(0.696 0.17 162.48)'],
        'chart-3' => ['oklch(0.398 0.07 227.392)', 'oklch(0.769 0.188 70.08)'],
        'chart-4' => ['oklch(0.64 0.15 84.429)', 'oklch(0.627 0.265 303.9)'],
        'chart-5' => ['oklch(0.64 0.16 70.08)', 'oklch(0.645 0.246 16.439)'],
        'sidebar' => ['oklch(0.985 0 0)', 'oklch(0.205 0 0)'],
        'sidebar-foreground' => ['oklch(0.145 0 0)', 'oklch(0.985 0 0)'],
        'sidebar-primary' => ['oklch(0.205 0 0)', 'oklch(0.488 0.243 264.376)'],
        'sidebar-primary-foreground' => ['oklch(0.985 0 0)', 'oklch(0.985 0 0)'],
        'sidebar-accent' => ['oklch(0.97 0 0)', 'oklch(0.269 0 0)'],
        'sidebar-accent-foreground' => ['oklch(0.205 0 0)', 'oklch(0.985 0 0)'],
        'sidebar-border' => ['oklch(0.922 0 0)', 'oklch(1 0 0 / 10%)'],
        'sidebar-ring' => ['oklch(0.708 0 0)', 'oklch(0.556 0 0)'],
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

        // No colours: the copper of app.css. Any colour: the app's look, the 40 tokens it left
        // unset at their values before copper, and the new tokens derived from that palette.
        $base = $set === [] ? [] : $set + self::PREVIOUS;
        $tokens = $base === [] ? [] : $base + $this->derive($base);

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
     * The new tokens, following the palette of an app that sets its own colours, so it wears
     * no copper in them. A derived value is not checked by isColor(), because color-mix()
     * fails it, but it only embeds colours that passed.
     *
     * @param  array<string, array{string, ?string}>  $set  token => [light or only value, dark or null]
     * @return array<string, array{string, ?string}>
     */
    private function derive(array $set): array
    {
        $background = $set['background'];
        // The brand bar and the focus ring are marks, so each reads at 3:1 on the page in its mode.
        $mark = $this->map($this->both($set['primary']), fn (string $c, string $mode): string => Contrast::readable($c, $this->ground($background, $mode), 3.0) ?? $c);
        $derived = [
            'brand' => $mark,
            'focus' => $mark,
            'skeleton' => $set['muted'],
            'chart-other' => $set['muted-foreground'],
            'sidebar-hover' => $set['sidebar-accent'],
        ];

        foreach (['primary', 'success', 'warning', 'info', 'destructive'] as $name) {
            if (isset($set[$name])) {
                $derived["{$name}-subtle"] = $this->map($set[$name], fn (string $c): string => "color-mix(in oklch, {$c} 14%, var(--background))");
                // The text gets a dark value even from a single colour: the tint follows the dark
                // background, so the light text would be too faint on it.
                $derived["{$name}-subtle-foreground"] = $this->map($this->both($set[$name]), fn (string $c, string $mode): string => $this->subtleForeground($c, $this->ground($background, $mode), $mode));
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
     * A pair with a dark value: a single colour sits in :root and so shows in dark mode too.
     *
     * @param  array{string, ?string}  $pair
     * @return array{string, string}
     */
    private function both(array $pair): array
    {
        return [$pair[0], $pair[1] ?? $pair[0]];
    }

    /**
     * The page background in a mode.
     *
     * @param  array{string, ?string}  $background
     */
    private function ground(array $background, string $mode): string
    {
        return $mode === 'light' ? $background[0] : $background[1] ?? $background[0];
    }

    /**
     * The colour at 4.5:1 on its -subtle tint, worked out here because CSS cannot search
     * for a contrast.
     */
    private function subtleForeground(string $color, string $ground, string $mode): string
    {
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
