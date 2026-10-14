<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Filters\Filter;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetList;
use Symfony\Component\HttpFoundation\Response;

class IndexPageController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): Response
    {
        $class = $this->viewableResource($request, $authorization);

        // A link may carry a filter the Resource no longer declares. The page drops it and
        // names it, so the person sees a banner; a hidden filter still counts as declared and 422s.
        $stale = [];
        $filters = $request->query('filter');

        if (is_array($filters)) {
            $resource = $class::make();
            $declared = array_map(fn (Filter $filter) => $filter->key(), [
                ...$resource->filters($request),
                ...array_map(fn (Field $field) => $field->makeFieldFilter(), $resource->filterableFields($request)),
            ]);
            $stale = array_values(array_diff(array_map(strval(...), array_keys($filters)), $declared));
            $kept = array_diff_key($filters, array_flip($stale));

            if ($kept === []) {
                $request->query->remove('filter');
            } else {
                $request->query->set('filter', $kept);
            }
        }

        // A bad parameter shows as an error on the page, not a redirect back.
        // Checking it reads no rows, so the deferred count request stays cheap.
        try {
            (new ResourceQuery($class::make(), $request))->validate();
        } catch (ValidationException $e) {
            $resource = $payload->resource($class);

            // The index draws the message as a banner with Reset, so the toolbar stays usable.
            return Inertia::render('Resources/Index', [
                'resource' => $resource,
                'table' => ['columns' => [], 'sortKeys' => [], 'rows' => [], 'rowKey' => $class::rowKey()],
                'filters' => ['available' => [], 'applied' => []],
                'tabs' => ['items' => [], 'active' => null],
                'lens' => null,
                'lenses' => [],
                'actions' => ['actions' => [], 'pivotActions' => [], 'counts' => ['sole' => 0, 'standalone' => 0, 'resource' => 0]],
                'state' => ['page' => 1, 'perPage' => $resource['perPageOptions'][0], 'hasMorePages' => false, 'sort' => ''],
                'error' => ['key' => array_key_first($e->errors()), 'message' => collect($e->errors())->flatten()->first()],
            ])->toResponse($request)->setStatusCode(422);
        }

        // Lazy props: the deferred count request reads only the count, not the rows.
        $data = null;
        $part = function (string $key) use (&$data, $payload, $class) {
            $data ??= $payload->build($class);

            return $data[$key];
        };

        return Inertia::render('Resources/Index', [
            'resource' => fn () => $part('resource'),
            'table' => fn () => $part('table'),
            'state' => fn () => $part('state'),
            'filters' => fn () => $part('filters'),
            'tabs' => fn () => $part('tabs'),
            'groups' => fn () => $part('groups'),
            'lens' => null,
            'lenses' => fn () => $part('lenses'),
            'actions' => fn () => $part('actions'),
            'staleFilters' => $stale,
            // The cards this user may see; each fetches its own value after the page paints.
            'widgets' => fn () => array_values(array_map(
                fn (Widget $widget) => $widget->descriptor(),
                array_filter(
                    (new WidgetList($class::make()->widgets($request), $class))->visible($request),
                    fn (Widget $widget) => $authorization->allowsWidget($request->user(), $class, $widget::uriKey()),
                ),
            )),
            // One group, so a single follow-up visit loads them all.
            'count' => Inertia::defer(fn () => $payload->count($class), 'counts'),
            'tabCounts' => Inertia::defer(fn () => $payload->tabCounts($class), 'counts'),
            'summaries' => Inertia::defer(fn () => $payload->summaries($class), 'counts'),
        ])->toResponse($request);
    }
}
