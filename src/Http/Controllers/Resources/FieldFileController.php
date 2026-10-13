<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\FieldContext;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Download and delete the file of one record's file field. The record comes from the
 * scoped query and the disk and path from the field and the stored record, never the request.
 */
class FieldFileController
{
    use AuthorizesResources;

    public function download(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $field): SymfonyResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));

        $target = $class::make($model)->fieldsFor(FieldContext::Detail, $request)
            ->forContext(FieldContext::Detail, $request, $model)
            ->authorized($request)
            ->findByAttribute($field);

        abort_unless($target instanceof FileField, 404);

        return $target->downloadResponse($request, $model);
    }

    /**
     * Delete the file and null its columns, leaving every other attribute. A dotted path such
     * as items.2.photo names a file inside the field's JSON value, read from the stored record.
     */
    public function destroy(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $field): Response
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'update', $model), 403, $authorization->denialMessage($class));

        $attribute = Str::before($field, '.');
        $nested = str_contains($field, '.') ? Str::after($field, '.') : null;

        $target = $class::make($model)->fieldsFor(FieldContext::Update, $request)
            ->forContext(FieldContext::Update, $request, $model)
            ->authorized($request)
            ->findByAttribute($attribute);

        abort_unless($target instanceof FileField && $target->isDeletable(), 404);

        if ($nested === null) {
            $path = $target->storedPath($model);

            foreach ($target->columnsThatShouldBeDeleted() as $column) {
                $model->setAttribute($column, null);
            }
        } else {
            $value = $model->getAttribute($attribute);
            abort_unless(is_array($value) && Arr::has($value, $nested), 404);

            $path = Arr::get($value, $nested);
            Arr::set($value, $nested, null);
            $model->setAttribute($attribute, $value);
        }

        $model->save();

        if (is_string($path) && $path !== '') {
            $target->deleteFile($request, $model, $path);
        }

        return new Response(status: 204);
    }
}
