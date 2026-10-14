<?php

namespace Lathe\Http\Controllers\Resources;

use Inertia\Inertia;
use Lathe\Actions\ActionMenu;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetList;
use Symfony\Component\HttpFoundation\Response;

class DetailPageController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $index, RecordPayload $payload, ActionMenu $menu, string $resource, string $resourceId): Response
    {
        $class = $this->viewableResource($request, $authorization);
        $query = new ResourceQuery($class::make(), $request, QueryContext::Detail);

        // A trashed record keeps its detail page, so it can be restored from there.
        if ($class::make()->definition()->usesSoftDeletes()) {
            $query = $query->withTrashed();
        }

        $model = $query->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));

        $breadcrumbs = $class::make($model)->detailBreadcrumbs($request) ?? $payload->breadcrumbs($class, [
            ['label' => '#'.$resourceId, 'href' => null],
        ]);

        return Inertia::render('Resources/Detail', [
            'resource' => $index->resource($class),
            'record' => $payload->detail($class, $model),
            'breadcrumbs' => $breadcrumbs,
            // The actions shown on detail, each flagged for this one record.
            'actions' => $menu->build($class, 'detail', $model),
            // The cards this user may see here, detail-only ones included; each fetches its own value.
            'widgets' => fn () => array_values(array_map(
                fn (Widget $widget) => $widget->descriptor(),
                array_filter(
                    (new WidgetList($class::make()->widgets($request), $class))->visible($request, true),
                    fn (Widget $widget) => $authorization->allowsWidget($request->user(), $class, $widget::uriKey()),
                ),
            )),
        ])->toResponse($request);
    }
}
