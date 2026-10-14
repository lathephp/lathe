<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Filters\Filter;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the lens the route names, then serves it through the index payload and pipeline.
 */
class LensController
{
    use AuthorizesResources;

    public function page(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): Response
    {
        [$class, $lens] = $this->viewableLens($request, $authorization);

        // As on the index: a bad parameter shows as an error on the page, and the check reads no rows.
        try {
            (new ResourceQuery($class::make(), $request))->forLens($lens)->validate();
        } catch (ValidationException $e) {
            return Inertia::render('Error', [
                'status' => 422,
                'message' => collect($e->errors())->flatten()->first(),
                'shell' => true,
            ])->toResponse($request)->setStatusCode(422);
        }

        $data = null;
        $part = function (string $key) use (&$data, $payload, $class, $lens) {
            $data ??= $payload->build($class, $lens);

            return $data[$key];
        };

        return Inertia::render('Resources/Index', [
            'resource' => fn () => $part('resource'),
            'table' => fn () => $part('table'),
            'state' => fn () => $part('state'),
            'filters' => fn () => $part('filters'),
            'tabs' => fn () => $part('tabs'),
            'lens' => fn () => $part('lens'),
            'lenses' => fn () => $part('lenses'),
            'actions' => fn () => $part('actions'),
            'count' => Inertia::defer(fn () => $payload->count($class, $lens), 'counts'),
        ])->toResponse($request);
    }

    public function index(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): JsonResponse
    {
        [$class, $lens] = $this->viewableLens($request, $authorization);

        return new JsonResponse($payload->build($class, $lens));
    }

    public function count(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $payload): JsonResponse
    {
        [$class, $lens] = $this->viewableLens($request, $authorization);

        return new JsonResponse($payload->count($class, $lens));
    }

    public function filters(LatheRequest $request, ResourceAuthorization $authorization): JsonResponse
    {
        [$class, $lens] = $this->viewableLens($request, $authorization);
        $threshold = $class::make()->definition()->getFilterRemoteThreshold();

        return new JsonResponse(['filters' => array_map(
            fn (Filter $filter) => $filter->definition($request, $threshold),
            $lens->availableFilters($request),
        )]);
    }

    /**
     * The Resource and the lens the route names: 404 for an unknown lens, 403 when canSee
     * or the viewLens ability refuses it.
     *
     * @return array{0: class-string<\Lathe\Resources\Resource>, 1: Lens}
     */
    protected function viewableLens(LatheRequest $request, ResourceAuthorization $authorization): array
    {
        $class = $this->viewableResource($request, $authorization);
        $lens = collect($class::make()->lenses($request))->first(fn (Lens $lens) => $lens->uriKey() === $request->route('lens'));

        abort_if($lens === null, 404);
        abort_unless($lens->authorizedToSee($request), 403);
        abort_unless($authorization->allowsLens($request->user(), $class, $lens), 403);

        return [$class, $lens];
    }
}
