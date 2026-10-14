<?php

namespace Lathe\Http\Controllers\Resources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Events\ResourceCreated;
use Lathe\Events\ResourceUpdated;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\TrafficCop;
use Lathe\Resources\ValidationPipeline;
use Lathe\Resources\Via;

/**
 * Create and update one record. Validation runs first; the before hook, the fill, the save,
 * the fields' after-save callbacks and the event share one transaction; the after hook runs
 * once it commits.
 */
class RecordController
{
    use AuthorizesResources;

    public function store(LatheRequest $request, ResourceAuthorization $authorization, string $resource): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);

        abort_unless($authorization->allows($request->user(), $class, 'create'), 403, $authorization->denialMessage($class));

        // Before validation, so the forced parent is what the rules check and the fill writes.
        Via::fromRequest($request, $class)?->force($request);

        $fields = ValidationPipeline::writable($class::make()->fieldsFor(FieldContext::Create, $request), FieldContext::Create, $request);
        (new ValidationPipeline($fields, $request, FieldContext::Create, $class))->validate();

        $model = $class::newModel();

        $model->getConnection()->transaction(function () use ($request, $class, $model, $fields) {
            $model->forceFill($class::defaultAttributes($request));
            $class::beforeCreate($request, $model);
            $this->fillAndSave($request, $model, $fields->all());
            event(new ResourceCreated($model, $class));
        });

        $class::afterCreate($request, $model);

        return new JsonResponse([
            'id' => $model->getKey(),
            'redirect' => $class::redirectAfterCreate($request, $class::make($model)),
        ], 201);
    }

    public function update(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Edit))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'update', $model), 403, $authorization->denialMessage($class));

        if ($class::make()->definition()->usesTrafficCop() && TrafficCop::isStale($model, $request->input('_updated_at'))) {
            return new JsonResponse([
                'error' => 'conflict',
                'message' => Lathe::__('This record was changed by someone else since you opened it.'),
                'updatedAt' => TrafficCop::token($model),
            ], 409);
        }

        $fields = ValidationPipeline::writable($class::make($model)->fieldsFor(FieldContext::Update, $request), FieldContext::Update, $request, $model);
        (new ValidationPipeline($fields, $request, FieldContext::Update, $class, $model))->validate();

        $model->getConnection()->transaction(function () use ($request, $class, $model, $fields) {
            $class::beforeUpdate($request, $model);
            $original = $model->getOriginal();
            $this->fillAndSave($request, $model, $fields->all());
            $changes = $model->getChanges();
            event(new ResourceUpdated($model, $class, array_intersect_key($original, $changes), $changes));
        });

        $class::afterUpdate($request, $model);

        return new JsonResponse([
            'id' => $model->getKey(),
            'redirect' => $class::redirectAfterUpdate($request, $class::make($model)),
            'updatedAt' => TrafficCop::token($model),
        ]);
    }

    /**
     * Fill the fields the request carries, save, then run what the fields deferred until after save.
     *
     * @param  array<int, Field>  $fields
     */
    protected function fillAndSave(LatheRequest $request, Model $model, array $fields): void
    {
        $callbacks = [];

        foreach ($fields as $field) {
            // A key the request leaves out keeps its stored value.
            if (! $request->exists($field->attribute())) {
                continue;
            }

            $callback = $field->fill($request, $model);

            if ($callback instanceof Closure) {
                $callbacks[] = $callback;
            }
        }

        $model->save();

        foreach ($callbacks as $callback) {
            $callback();
        }
    }
}
