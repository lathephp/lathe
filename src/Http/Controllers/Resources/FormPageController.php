<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\IndexPayload;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Via;
use Symfony\Component\HttpFoundation\Response;

/**
 * The create and edit pages. Each checks the ability its submit needs, so a refused user
 * sees the 403 page instead of a form that cannot save.
 */
class FormPageController
{
    use AuthorizesResources;

    public function create(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $index, RecordPayload $payload): Response
    {
        $class = $this->viewableResource($request, $authorization);

        abort_unless($authorization->allows($request->user(), $class, 'create'), 403, $authorization->denialMessage($class));

        // A page has no 422 to show, so a via naming no parent the user may add to is not found.
        try {
            $via = Via::fromRequest($request, $class);
        } catch (ValidationException) {
            abort(404);
        }

        return Inertia::render('Resources/Form', [
            'resource' => $index->resource($class),
            'mode' => 'create',
            'id' => null,
            'title' => $class::createButtonLabel(),
            'breadcrumbs' => $payload->breadcrumbs($class, [['label' => $class::createButtonLabel(), 'href' => null]]),
            'form' => $payload->creationFields($class, $via),
            'submitLabel' => $class::createButtonLabel(),
        ])->toResponse($request);
    }

    public function edit(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $index, RecordPayload $payload, string $resource, string $resourceId): Response
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'update', $model), 403, $authorization->denialMessage($class));

        return Inertia::render('Resources/Form', [
            'resource' => $index->resource($class),
            'mode' => 'update',
            'id' => $model->getKey(),
            'title' => $class::make($model)->title(),
            'breadcrumbs' => $payload->breadcrumbs($class, [
                ['label' => '#'.$resourceId, 'href' => $payload->href('resources/'.$class::uriKey().'/'.$resourceId)],
                ['label' => Lathe::__('Edit'), 'href' => null],
            ]),
            'form' => $payload->updateFields($class, $model),
            'submitLabel' => $class::updateButtonLabel(),
        ])->toResponse($request);
    }

    public function replicate(LatheRequest $request, ResourceAuthorization $authorization, IndexPayload $index, RecordPayload $payload, string $resource, string $resourceId): Response
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Replicate))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'replicate', $model), 403, $authorization->denialMessage($class));

        return Inertia::render('Resources/Form', [
            'resource' => $index->resource($class),
            'mode' => 'replicate',
            'id' => $model->getKey(),
            'title' => $class::createButtonLabel(),
            'breadcrumbs' => $payload->breadcrumbs($class, [
                ['label' => '#'.$resourceId, 'href' => $payload->href('resources/'.$class::uriKey().'/'.$resourceId)],
                ['label' => Lathe::__('Replicate'), 'href' => null],
            ]),
            'form' => $payload->replicateFields($class, $model),
            'submitLabel' => $class::createButtonLabel(),
        ])->toResponse($request);
    }
}
