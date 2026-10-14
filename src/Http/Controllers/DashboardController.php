<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lathe\Dashboards\Dashboard;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Http\Requests\LatheRequest;
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
        $widgets = (new WidgetList($found->widgets($request), $found::class))->visible($request);
        $values = [];

        // Each card's value is its own deferred group, so the cards fill in in parallel.
        foreach ($widgets as $widget) {
            if ($widget->requestsValue()) {
                $values['widgetValue:'.$widget::uriKey()] = Inertia::defer(fn () => $widget->withCacheOwner('dashboard:'.$dashboard)->cachedResponse($request), $widget::uriKey());
            }
        }

        return Inertia::render('Dashboard', [
            'dashboard' => $found->payload(),
            'widgets' => array_map(fn (Widget $widget) => $widget->descriptor(), $widgets),
            ...$values,
        ]);
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
