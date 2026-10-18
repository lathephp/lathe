<?php

namespace Lathe\Preferences;

use Illuminate\Support\Arr;
use Lathe\Enums\CardWidth;
use Lathe\Widgets\Widget;

/**
 * A user's layout of a dashboard (order, width and hidden flag per widget), aged against the
 * widgets the code has now: a removed widget drops out, and a new one joins at the end, shown.
 */
class DashboardLayout
{
    /**
     * The preference key a dashboard's layout is saved under.
     */
    public static function key(string $uriKey): string
    {
        return "dashboard:{$uriKey}";
    }

    /**
     * The width each widget has in code, by uriKey, in code order.
     *
     * @internal
     *
     * @param  list<Widget>  $widgets
     * @return array<string, string>
     */
    public static function codeWidths(array $widgets): array
    {
        $widths = [];

        foreach ($widgets as $widget) {
            $widths[$widget::uriKey()] = Arr::string($widget->descriptor(), 'width');
        }

        return $widths;
    }

    /**
     * The saved entries of widgets still in code, each once and in saved order, then the code
     * widgets the layout lacks, shown, at their code width.
     *
     * @param  array<array-key, mixed>  $saved
     * @param  array<string, string>  $codeWidths  uriKey => width of the widgets the user can see
     * @return list<array{uriKey: string, width: string, hidden: bool}>
     */
    public static function age(array $saved, array $codeWidths): array
    {
        $layout = [];

        foreach ($saved as $entry) {
            $key = is_array($entry) ? ($entry['uriKey'] ?? null) : null;

            if (! is_string($key) || ! isset($codeWidths[$key]) || isset($layout[$key])) {
                continue;
            }

            $width = $entry['width'] ?? null;

            $layout[$key] = [
                'uriKey' => $key,
                'width' => is_string($width) && CardWidth::tryFrom($width) !== null ? $width : $codeWidths[$key],
                'hidden' => ($entry['hidden'] ?? false) === true,
            ];
        }

        foreach ($codeWidths as $key => $width) {
            $layout[$key] ??= ['uriKey' => $key, 'width' => $width, 'hidden' => false];
        }

        return array_values($layout);
    }
}
