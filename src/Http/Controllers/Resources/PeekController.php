<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\FieldContext;
use Lathe\Http\Payloads\RecordPayload;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;

/**
 * The peek and preview of one record as JSON, behind the view ability.
 */
class PeekController
{
    use AuthorizesResources;

    public function peek(LatheRequest $request, ResourceAuthorization $authorization, RecordPayload $payload, string $resource, string $resourceId): JsonResponse
    {
        return $this->respond($request, $authorization, $payload, $resourceId, FieldContext::Peeking);
    }

    public function preview(LatheRequest $request, ResourceAuthorization $authorization, RecordPayload $payload, string $resource, string $resourceId): JsonResponse
    {
        return $this->respond($request, $authorization, $payload, $resourceId, FieldContext::Preview);
    }

    protected function respond(LatheRequest $request, ResourceAuthorization $authorization, RecordPayload $payload, string $resourceId, FieldContext $context): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));

        return new JsonResponse($payload->summary($class, $model, $context));
    }
}
