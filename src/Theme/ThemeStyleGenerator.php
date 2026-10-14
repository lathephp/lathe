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

        foreach (Color::cases() as $color) {
            $value = $colors[$color->value] ?? null;

            if (is_string($value) && static::isColor($value)) {
                $light[] = "{$color->variable()}:{$value}";
            } elseif (is_array($value) && is_string($value['light'] ?? null) && is_string($value['dark'] ?? null)
                && static::isColor($value['light']) && static::isColor($value['dark'])) {
                $light[] = "{$color->variable()}:{$value['light']}";
                $dark[] = "{$color->variable()}:{$value['dark']}";
            }
        }

        return ':root{'.implode(';', $light).'}'.($dark === [] ? '' : '.dark{'.implode(';', $dark).'}');
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
