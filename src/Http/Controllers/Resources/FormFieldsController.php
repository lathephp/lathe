<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The create and edit forms as JSON, behind the abilities their submits need.
 */
class FormFieldsController
{
    use AuthorizesResources;

    public function creation(LatheRequest $request, ResourceAuthorization $authorization, RecordPayload $payload): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);

        abort_unless($authorization->allows($request->user(), $class, 'create'), 403, $authorization->denialMessage($class));

        return new JsonResponse($payload->creationFields($class));
    }

    public function update(LatheRequest $request, ResourceAuthorization $authorization, RecordPayload $payload, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'update', $model), 403, $authorization->denialMessage($class));

        return new JsonResponse($payload->updateFields($class, $model));
    }
}
