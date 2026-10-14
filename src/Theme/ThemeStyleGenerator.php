<?php

namespace Lathe\Theme;

/**
 * The CSS of theme.radius and theme.colors: a :root block for the light values and a
 * .dark block for the dark half of each {light, dark} pair. A single colour string
 * sits in :root only, and wins in dark mode too because the tag follows the stylesheets.
 */
class ThemeStyleGenerator
{
    /**
     * @param  array<array-key, mixed>  $colors  token => colour, ['light' => ..., 'dark' => ...] or null
     */
    public function generate(string $radius, array $colors): string
    {
        $light = ["--radius:{$radius}"];
        $dark = [];

        foreach (Color::cases() as $color) {
            $value = $colors[$color->value] ?? null;

            if (is_string($value)) {
                $light[] = "{$color->variable()}:{$value}";
            } elseif (is_array($value) && is_string($value['light'] ?? null) && is_string($value['dark'] ?? null)) {
                $light[] = "{$color->variable()}:{$value['light']}";
                $dark[] = "{$color->variable()}:{$value['dark']}";
            }
        }

        return ':root{'.implode(';', $light).'}'.($dark === [] ? '' : '.dark{'.implode(';', $dark).'}');
    }
}
