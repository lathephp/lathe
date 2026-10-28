<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lathe\Actions\ActionMenu;
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
        $class = $this->viewableResource($request, $authorization);
        $model = $this->record($request, $authorization, $class, $resourceId);

        return new JsonResponse($payload->summary($class, $model, FieldContext::Peeking));
    }

    /**
     * The preview panel's record: the summary plus what the record's page offers, its
     * abilities and the actions menu, so the panel shows the same Edit and actions.
     */
    public function preview(LatheRequest $request, ResourceAuthorization $authorization, RecordPayload $payload, ActionMenu $menu, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->record($request, $authorization, $class, $resourceId);

        return new JsonResponse([
            ...$payload->summary($class, $model, FieldContext::Preview),
            'abilities' => $payload->abilities($class, $model),
            'actions' => $menu->build($class, 'detail', $model),
        ]);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function record(LatheRequest $request, ResourceAuthorization $authorization, string $class, string $resourceId): Model
    {
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));

        return $model;
    }
}
