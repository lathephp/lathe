<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lathe\Dashboards\Dashboard;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Preferences\DashboardLayout;
use Lathe\Preferences\UserPreference;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetList;

class DashboardController
{
    public function __construct(protected DashboardRegistry $dashboards)
    {
        //
    }

    /**
     * The panel home: the dashboard at the key main.
     */
    public function __invoke(LatheRequest $request): Response
    {
        return $this->page($request, 'main');
    }

    public function page(LatheRequest $request, string $dashboard): Response
    {
        $found = $this->find($request, $dashboard);
        $visible = (new WidgetList($found->widgets($request), $found::class))->visible($request);
        [$widgets, $hidden, $widths] = $this->arrange($request, $found, $visible);
        $range = $found->selectedRange($request);
        $values = [];

        // Each card's value is its own deferred group, so the cards fill in in parallel.
        foreach ($widgets as $widget) {
            if ($widget->requestsValue()) {
                $values['widgetValue:'.$widget::uriKey()] = Inertia::defer(fn () => $widget->withCacheOwner('dashboard:'.$dashboard)->responseForRange($request, $range), $widget::uriKey());
            }
        }

        return Inertia::render('Dashboard', [
            'dashboard' => [...$found->payload(), 'range' => $range, 'customized' => $widths !== null],
            'widgets' => array_map(fn (Widget $widget) => $this->descriptor($widget, $widths), $widgets),
            'hiddenWidgets' => array_map(fn (Widget $widget) => $this->descriptor($widget, $widths), $hidden),
            ...$values,
        ]);
    }

    /**
     * The widgets to show and the ones the user hid, with the saved width of each by uriKey. With
     * no saved layout, or a dashboard that is not customizable, that is the code's order and widths.
     *
     * @param  list<Widget>  $visible
     * @return array{0: list<Widget>, 1: list<Widget>, 2: array<string, string>|null}
     */
    protected function arrange(LatheRequest $request, Dashboard $dashboard, array $visible): array
    {
        $saved = $dashboard->isCustomizable()
            ? UserPreference::where('user_id', UserPreference::userId($request))->where('key', DashboardLayout::key($dashboard::uriKey()))->first()
            : null;

        if ($saved === null) {
            return [$visible, [], null];
        }

        $byKey = [];

        foreach ($visible as $widget) {
            $byKey[$widget::uriKey()] = $widget;
        }

        $shown = $hidden = $widths = [];

        foreach (DashboardLayout::age($saved->value, DashboardLayout::codeWidths($visible)) as $entry) {
            $widths[$entry['uriKey']] = $entry['width'];

            if ($entry['hidden']) {
                $hidden[] = $byKey[$entry['uriKey']];
            } else {
                $shown[] = $byKey[$entry['uriKey']];
            }
        }

        return [$shown, $hidden, $widths];
    }

    /**
     * The card's descriptor at its saved width, laid over a copy so the widget keeps its code width.
     *
     * @param  array<string, string>|null  $widths
     * @return array<string, mixed>
     */
    protected function descriptor(Widget $widget, ?array $widths): array
    {
        $width = $widths[$widget::uriKey()] ?? null;

        return $width === null
            ? $widget->descriptor()
            : [...$widget->descriptor(), 'width' => $width, 'columns' => CardWidth::from($width)->columns()];
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse([
            'dashboards' => array_map(fn (Dashboard $dashboard) => $dashboard->payload(), $this->dashboards->authorized($request)),
        ]);
    }

    public function show(Request $request, string $dashboard): JsonResponse
    {
        return new JsonResponse(['dashboard' => $this->find($request, $dashboard)->payload()]);
    }

    /**
     * Unknown keys answer 404; a dashboard the user may not see answers 403.
     */
    protected function find(Request $request, string $uriKey): Dashboard
    {
        $dashboard = $this->dashboards->forKey($uriKey);

        abort_if($dashboard === null, 404);
        abort_unless($dashboard->authorize($request), 403);

        return $dashboard;
    }
}
