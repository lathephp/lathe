<?php

namespace Lathe\Http\Controllers\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Events\ResourceDeleted;
use Lathe\Events\ResourceForceDeleted;
use Lathe\Events\ResourceRestored;
use Lathe\Field\FieldContext;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Uploads\FieldAttachment;

/**
 * Delete, force delete and restore one record. Each runs its before hook, the write and
 * its event in one transaction, then its after hook once the transaction commits.
 */
class DeleteController
{
    use AuthorizesResources;

    public function delete(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->authorizedModel($request, $authorization, $class, $resourceId, 'delete', false);

        return $this->run($request, $class, $model, 'Delete', function () use ($model): void {
            $model->delete();
        }, ResourceDeleted::class, purge: true);
    }

    public function forceDelete(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->authorizedModel($request, $authorization, $class, $resourceId, 'forceDelete', true);

        return $this->run($request, $class, $model, 'ForceDelete', function () use ($model): void {
            $model->forceDelete();
        }, ResourceForceDeleted::class, purge: true);
    }

    public function restore(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = $this->authorizedModel($request, $authorization, $class, $resourceId, 'restore', true);

        // A model without soft deletes has nothing to restore.
        abort_unless(method_exists($model, 'restore'), 404);

        return $this->run($request, $class, $model, 'Restore', function () use ($model): void {
            $model->restore();
        }, ResourceRestored::class);
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
     * @param  Closure(): void  $write
     * @param  class-string<ResourceDeleted|ResourceForceDeleted|ResourceRestored>  $event
     */
    protected function run(LatheRequest $request, string $class, Model $model, string $hook, Closure $write, string $event, bool $purge = false): JsonResponse
    {
        $model->getConnection()->transaction(function () use ($request, $class, $model, $hook, $write, $event) {
            $class::{'before'.$hook}($request, $model);
            $write();
            event(new $event($model, $class));
        });

        $class::{'after'.$hook}($request, $model);

        // Files cannot roll back with the transaction, so they go only once the delete has committed.
        if ($purge) {
            $this->purgeAttachments($model);
        }

        return new JsonResponse(['redirect' => $class::redirectAfterDelete($request)]);
    }

    /**
     * Removes the files of the record's prunable file fields and its editor attachments, after the delete commits.
     */
    protected function purgeAttachments(Model $model): void
    {
        // A soft-deleted model still exists and keeps its files for a restore.
        if ($model->exists) {
            return;
        }

        $request = app(LatheRequest::class);

        foreach ($request->resourceOrFail()::make($model)->fieldsFor(FieldContext::Detail, $request) as $field) {
            if ($field instanceof FileField && $field->isPrunable()) {
                $field->deleteFile($request, $model);
            }
        }

        foreach (FieldAttachment::of($model)->get() as $attachment) {
            $attachment->purge();
        }
    }
}
