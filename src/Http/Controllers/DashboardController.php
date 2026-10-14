<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lathe\Dashboards\Dashboard;
use Lathe\Dashboards\DashboardRegistry;

class DashboardController
{
    public function __construct(protected DashboardRegistry $dashboards)
    {
        //
    }

    /**
     * The panel home: the dashboard at the key main.
     */
    public function __invoke(Request $request): Response
    {
        return $this->page($request, 'main');
    }

    public function page(Request $request, string $dashboard): Response
    {
        $found = $this->find($request, $dashboard);

        return Inertia::render('Dashboard', [
            'dashboard' => $found->payload(),
            'widgets' => [],
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
