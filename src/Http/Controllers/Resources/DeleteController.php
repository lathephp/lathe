<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\RecordDeleter;

/**
 * Delete, force delete and restore one record, through the RecordDeleter the batch routes share.
 */
class DeleteController
{
    use AuthorizesResources;

    public function delete(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->authorizedModel($request, $authorization, $class, $resourceId, 'delete', false);

        $deleter->delete($request, $class, $model);

        return $this->done($request, $class);
    }

    public function forceDelete(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->authorizedModel($request, $authorization, $class, $resourceId, 'forceDelete', true);

        $deleter->forceDelete($request, $class, $model);

        return $this->done($request, $class);
    }

    public function restore(LatheRequest $request, ResourceAuthorization $authorization, RecordDeleter $deleter, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->authorizedModel($request, $authorization, $class, $resourceId, 'restore', true);

        // A model without soft deletes has nothing to restore.
        abort_unless(method_exists($model, 'restore'), 404);

        $deleter->restore($request, $class, $model);

        return $this->done($request, $class);
    }

    /**
     * The record through the Detail query: 404 outside its scope, then 403 without the ability.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function authorizedModel(LatheRequest $request, ResourceAuthorization $authorization, string $class, string $id, string $ability, bool $withTrashed): Model
    {
        $query = new ResourceQuery($class::make(), $request, QueryContext::Detail);
        $model = ($withTrashed ? $query->withTrashed() : $query)->find($id);

        abort_unless($authorization->allows($request->user(), $class, $ability, $model), 403, $authorization->denialMessage($class));

        return $model;
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function done(LatheRequest $request, string $class): JsonResponse
    {
        return new JsonResponse(['redirect' => $class::redirectAfterDelete($request)]);
    }
}
