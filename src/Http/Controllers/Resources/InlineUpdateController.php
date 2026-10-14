<?php

namespace Lathe\Http\Controllers\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Events\ResourceUpdated;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\FieldResolver;
use Lathe\Field\Relations\MorphToField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\TrafficCop;
use Lathe\Resources\ValidationPipeline;

/**
 * Save one index cell in place: the full update flow for one field, validated by that
 * field's rules alone, answering with the row's fresh cells and its new token.
 */
class InlineUpdateController
{
    use AuthorizesResources;

    public function __invoke(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $field): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($resourceId);

        abort_unless(
            $authorization->allows($request->user(), $class, 'update', $model) && $authorization->allows($request->user(), $class, 'inlineEdit', $model),
            403,
            $authorization->denialMessage($class),
        );

        $columns = $class::make($model)->fieldsFor(FieldContext::Index, $request)->forContext(FieldContext::Index, $request)->authorized($request);
        $target = $columns->first(fn (Field $column) => $column->key() === $field && $column->isInlineEditable($request));

        abort_if($target === null, 403, Lathe::__('This field cannot be edited inline.'));

        // Only the edited cell is the user's to write; its conditions read the rest from the record.
        $request->attributes->set('lathe.writable', [$target->attribute()]);
        // A morphTo cell sends its type beside the record key.
        $request->merge([$target->attribute() => $target instanceof MorphToField
            ? ['type' => $request->input('type'), 'id' => $request->input('value')]
            : $request->input('value')]);
        abort_if($target->isReadonly($request, $model) || ! $target->isVisibleFor($request, $model), 403, Lathe::__('This field cannot be edited inline.'));

        if ($class::make()->definition()->usesTrafficCop() && TrafficCop::isStale($model, $request->input('_updated_at'))) {
            return new JsonResponse([
                'error' => 'conflict',
                'message' => Lathe::__('This record was changed by someone else since you opened it.'),
                'updatedAt' => TrafficCop::token($model),
                'fields' => FieldResolver::cells($model, $columns),
            ], 409);
        }

        // The value took the field's own key above, so the normal parse and validate path runs.
        (new ValidationPipeline(new FieldCollection([$target]), $request, FieldContext::Update, $class, $model))->validate();

        $this->save($request, $class, $model, $target);

        return new JsonResponse([
            'field' => $target->key(),
            'fields' => FieldResolver::cells($model, $columns),
            'updatedAt' => TrafficCop::token($model),
        ]);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    protected function save(LatheRequest $request, string $class, Model $model, Field $field): void
    {
        $model->getConnection()->transaction(function () use ($request, $class, $model, $field) {
            $class::beforeUpdate($request, $model);
            $original = $model->getOriginal();
            $callback = $field->fill($request, $model);
            $model->save();

            if ($callback instanceof Closure) {
                $callback();
            }

            $changes = $model->getChanges();
            event(new ResourceUpdated($model, $class, array_intersect_key($original, $changes), $changes));
        });

        $class::afterUpdate($request, $model);
    }
}
