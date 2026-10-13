<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Symfony\Component\HttpFoundation\Response;

class IndexPageController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): Response
    {
        $class = $this->viewableResource($request, $authorization);

        // A bad parameter shows as an error on the page, not a redirect back.
        // Checking it reads no rows, so the deferred count request stays cheap.
        try {
            (new ResourceQuery($class::make(), $request))->validate();
        } catch (ValidationException $e) {
            $resource = $payload->resource($class);

            // The index draws the message as a banner with Reset, so the toolbar stays usable.
            return Inertia::render('Resources/Index', [
                'resource' => $resource,
                'table' => ['columns' => [], 'sortKeys' => [], 'rows' => [], 'rowKey' => 'id'],
                'filters' => ['available' => [], 'applied' => []],
                'tabs' => ['items' => [], 'active' => null],
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
            // One group, so a single follow-up visit loads both.
            'count' => Inertia::defer(fn () => $payload->count($class), 'counts'),
            'tabCounts' => Inertia::defer(fn () => $payload->tabCounts($class), 'counts'),
        ])->toResponse($request);
    }
}
