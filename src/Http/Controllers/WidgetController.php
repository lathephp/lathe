<?php

namespace Lathe\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Http\Controllers\Resources\AuthorizesResources;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Support\Timezone;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetList;

/**
 * Widget lists and values of dashboards, Resource pages and lenses. An unknown key
 * answers 404; a key the user may not see answers 403.
 */
class WidgetController
{
    use AuthorizesResources;

    public function __construct(protected DashboardRegistry $dashboards, protected ResourceAuthorization $authorization)
    {
        //
    }

    public function dashboardIndex(LatheRequest $request, string $dashboard): JsonResponse
    {
        return $this->list($request, $this->dashboardWidgets($request, $dashboard));
    }

    public function dashboardShow(LatheRequest $request, string $dashboard, string $widget): JsonResponse
    {
        return $this->value($request, $this->dashboardWidgets($request, $dashboard), $widget, owner: 'dashboard:'.$dashboard, range: $this->pickedRange($request, $dashboard));
    }

    /**
     * The request's range when the dashboard's picker offers it, which each card maps onto
     * its own; null passes any other range through.
     *
     * @internal
     */
    protected function pickedRange(LatheRequest $request, string $dashboard): ?string
    {
        $range = $request->query('range');

        return is_string($range) && in_array($range, array_map(strval(...), array_keys($this->dashboards->forKey($dashboard)?->ranges() ?? [])), true) ? $range : null;
    }

    /**
     * The rows or series a chart or table card shows, as a CSV download. A card without
     * a CSV answers 404.
     */
    public function dashboardCsv(LatheRequest $request, string $dashboard, string $widget): Response
    {
        $found = $this->dashboardWidgets($request, $dashboard)->find($widget);

        abort_if($found === null || ! $found->requestsValue() || $found->isOnlyOnDetail(), 404);
        abort_unless($found->authorize($request), 403);

        $csv = $found->withCacheOwner('dashboard:'.$dashboard)->csv($request, $this->pickedRange($request, $dashboard));

        abort_if($csv === null, 404);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$found::uriKey().'-'.CarbonImmutable::now(app(Timezone::class)->forRequest($request))->toDateString().'.csv"',
        ]);
    }

    public function resourceIndex(LatheRequest $request): JsonResponse
    {
        [$class, $list, $detail] = $this->resourceWidgets($request);

        return $this->list($request, $list, $detail, fn (Widget $widget) => $this->authorization->allowsWidget($request->user(), $class, $widget::uriKey()));
    }

    public function resourceShow(LatheRequest $request, string $resource, string $widget): JsonResponse
    {
        [$class, $list, $detail] = $this->resourceWidgets($request);

        $resourceId = $detail ? $request->query('resourceId') : '';

        return $this->value(
            $request, $list, $widget, $detail,
            fn (Widget $found) => $this->authorization->allowsWidget($request->user(), $class, $found::uriKey()),
            'resource:'.$class::uriKey().':'.(is_string($resourceId) ? $resourceId : ''),
            fn (Widget $found) => $found
                ->withResourceScope($class::newModel()::class, fn (Builder $query) => $class::widgetQuery($request, $query))
                ->withFilters(collect($class::make()->availableFilters($request)), $class::make()),
        );
    }

    public function lensIndex(LatheRequest $request): JsonResponse
    {
        [$class, $lens] = $this->viewableLens($request);

        return $this->list($request, new WidgetList($lens->widgets($request), $lens::class), false, fn (Widget $widget) => $this->authorization->allowsWidget($request->user(), $class, $widget::uriKey()));
    }

    public function lensShow(LatheRequest $request, string $resource, string $lens, string $widget): JsonResponse
    {
        [$class, $found] = $this->viewableLens($request);

        return $this->value(
            $request, new WidgetList($found->widgets($request), $found::class), $widget, false,
            fn (Widget $w) => $this->authorization->allowsWidget($request->user(), $class, $w::uriKey()),
            'lens:'.$class::uriKey().':'.$found->uriKey(),
            fn (Widget $w) => $w
                ->withResourceScope($class::newModel()::class, fn (Builder $query) => $found::query($request, $class::widgetQuery($request, $query)))
                ->withFilters(collect($found->availableFilters($request)), $class::make()),
        );
    }

    /**
     * @param  (callable(Widget): bool)|null  $allows
     */
    protected function list(LatheRequest $request, WidgetList $list, bool $detail = false, ?callable $allows = null): JsonResponse
    {
        $widgets = array_filter($list->visible($request, $detail), fn (Widget $widget) => $allows === null || $allows($widget));

        return new JsonResponse(['widgets' => array_values(array_map(fn (Widget $widget) => $widget->descriptor(), $widgets))]);
    }

    /**
     * @param  (callable(Widget): bool)|null  $allows
     * @param  string  $owner  where the card is mounted, for its cache key
     * @param  (callable(Widget): mixed)|null  $scope  binds the host Resource's row scope
     * @param  string|null  $range  a dashboard's range, which the card maps onto its own
     */
    protected function value(LatheRequest $request, WidgetList $list, string $uriKey, bool $detail = false, ?callable $allows = null, string $owner = '', ?callable $scope = null, ?string $range = null): JsonResponse
    {
        $widget = $list->find($uriKey);

        abort_if($widget === null || ! $widget->requestsValue() || ($widget->isOnlyOnDetail() && ! $detail), 404);
        abort_unless($widget->authorize($request) && ($allows === null || $allows($widget)), 403);

        if ($scope !== null) {
            $scope($widget);
        }

        $widget->withCacheOwner($owner);

        return new JsonResponse($range === null ? $widget->cachedResponse($request) : $widget->responseForRange($request, $range));
    }

    protected function dashboardWidgets(LatheRequest $request, string $uriKey): WidgetList
    {
        $dashboard = $this->dashboards->forKey($uriKey);

        abort_if($dashboard === null, 404);
        abort_unless($dashboard->authorize($request), 403);

        return new WidgetList($dashboard->widgets($request), $dashboard::class);
    }

    /**
     * The Resource's widgets; with ?resourceId, the detail page's, after view on that record.
     *
     * @return array{0: class-string<\Lathe\Resources\Resource>, 1: WidgetList, 2: bool}
     */
    protected function resourceWidgets(LatheRequest $request): array
    {
        $class = $this->viewableResource($request, $this->authorization);
        $resourceId = $request->query('resourceId');
        $detail = is_string($resourceId) && $resourceId !== '';

        if ($detail) {
            $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

            abort_unless($this->authorization->allows($request->user(), $class, 'view', $model), 403, $this->authorization->denialMessage($class));
        }

        return [$class, new WidgetList($class::make()->widgets($request), $class), $detail];
    }

    /**
     * @return array{0: class-string<\Lathe\Resources\Resource>, 1: Lens}
     */
    protected function viewableLens(LatheRequest $request): array
    {
        $class = $this->viewableResource($request, $this->authorization);
        $lens = collect($class::make()->lenses($request))->first(fn (Lens $lens) => $lens->uriKey() === $request->route('lens'));

        abort_if($lens === null, 404);
        abort_unless($lens->authorizedToSee($request), 403);
        abort_unless($this->authorization->allowsLens($request->user(), $class, $lens), 403);

        return [$class, $lens];
    }
}
