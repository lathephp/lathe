<?php

namespace Lathe\Http\Controllers\Resources;

use Inertia\Inertia;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Symfony\Component\HttpFoundation\Response;

class DetailPageController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $index, RecordPayload $payload, string $resource, string $resourceId): Response
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
            'actions' => [],
            'widgets' => [],
        ])->toResponse($request);
    }
}
