<?php

namespace Lathe\Widgets;

use Illuminate\Http\Request;
use Lathe\Exceptions\LatheException;

/**
 * One mount point's widgets: checked for repeated keys, then filtered for the request.
 */
class WidgetList
{
    /** @var array<string, Widget> */
    protected array $widgets = [];

    /**
     * @param  array<mixed>  $widgets  what a widgets() method returned
     */
    public function __construct(array $widgets, string $owner)
    {
        foreach ($widgets as $widget) {
            if (! $widget instanceof Widget) {
                throw new LatheException(sprintf('%s::widgets() returned a %s; it must return Widget instances.', $owner, get_debug_type($widget)));
            }

            $key = $widget::uriKey();

            if (isset($this->widgets[$key])) {
                throw new LatheException(sprintf(
                    'Widgets %s and %s of %s share the key "%s". Give one of them a distinct uriKey().',
                    $this->widgets[$key]::class, $widget::class, $owner, $key,
                ));
            }

            $this->widgets[$key] = $widget;
        }
    }

    /**
     * The widgets the request may see; detail-only ones only on a detail page.
     *
     * @return list<Widget>
     */
    public function visible(Request $request, bool $detail = false): array
    {
        return array_values(array_filter(
            $this->widgets,
            fn (Widget $widget) => $widget->authorize($request) && ($detail || ! $widget->isOnlyOnDetail()),
        ));
    }

    public function find(string $uriKey): ?Widget
    {
        return $this->widgets[$uriKey] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function descriptors(Request $request, bool $detail = false): array
    {
        return array_map(fn (Widget $widget) => $widget->descriptor(), $this->visible($request, $detail));
    }
}
