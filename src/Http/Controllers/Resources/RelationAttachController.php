<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Events\ResourceAttached;
use Lathe\Events\ResourceAttachmentUpdated;
use Lathe\Events\ResourceDetached;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\Associatable;
use Lathe\Field\Relations\BelongsToManyField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;
use Lathe\Rules\NotAttached;
use Lathe\Rules\NotExactlyAttached;

/**
 * Attach, detach and edit the pivot rows of a parent's belongsToMany, morphToMany or
 * morphedByMany relation. Each write and its event share one transaction.
 */
class RelationAttachController
{
    use AuthorizesResources;

    public function attach(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $relationship): JsonResponse
    {
        [$class, $parent, $field, $relation] = $this->resolve($request, $authorization, $resourceId, $relationship);
        [$related, $key] = $this->relatedRecord($request, $class, $field);

        $this->authorizeIds($request, $authorization, $class, $parent, 'attach'.Str::studly($relationship), [$key]);

        $fields = $this->pivotFields($request, $field, $related);
        $pivot = $this->pivotValues($request, $fields);

        $this->validatePivot($request, $fields, FieldContext::Create, [
            'related_id' => [$field->allowsDuplicateRelations() ? new NotExactlyAttached($relation, $pivot) : new NotAttached($relation)],
        ]);

        $parent->getConnection()->transaction(function () use ($parent, $class, $relationship, $relation, $key, $pivot) {
            $relation->attach($key, $pivot);
            event(new ResourceAttached($parent, $class, $relationship, [$key], $pivot));
        });

        return new JsonResponse(['attached' => true], 201);
    }

    public function detach(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $relationship, string $relatedId): JsonResponse
    {
        [$class, $parent, , $relation] = $this->resolve($request, $authorization, $resourceId, $relationship);
        [$key] = $this->attachedRow($request, $relation, $relatedId);

        $this->authorizeIds($request, $authorization, $class, $parent, 'detach'.Str::studly($relationship), [$key]);

        $parent->getConnection()->transaction(function () use ($parent, $class, $relationship, $relation, $key) {
            $relation->detach($key);
            event(new ResourceDetached($parent, $class, $relationship, [$key]));
        });

        return new JsonResponse(['detached' => true]);
    }

    public function updatePivot(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $relationship, string $relatedId): JsonResponse
    {
        [$class, $parent, $field, $relation] = $this->resolve($request, $authorization, $resourceId, $relationship);
        [$key, $row] = $this->attachedRow($request, $relation, $relatedId);

        $this->authorizeIds($request, $authorization, $class, $parent, 'attach'.Str::studly($relationship), [$key]);

        $fields = $this->pivotFields($request, $field, (new Associatable($request, $class, $field))->query()?->option((string) $key));
        $this->validatePivot($request, $fields, FieldContext::Update);

        $changes = $this->pivotValues($request, $fields);
        $original = array_intersect_key($row, $changes);

        $parent->getConnection()->transaction(function () use ($parent, $class, $relationship, $relation, $key, $original, $changes) {
            $relation->updateExistingPivot($key, $changes);
            event(new ResourceAttachmentUpdated($parent, $class, $relationship, [$key], $original, $changes));
        });

        return new JsonResponse(['updated' => true]);
    }

    /**
     * The attach modal's pivot form: the pivot fields with their defaults. Needs view.
     */
    public function pivotFieldsForAttach(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $relationship): JsonResponse
    {
        [$class, $parent, $field] = $this->resolve($request, $authorization, $resourceId, $relationship);

        abort_unless($authorization->allows($request->user(), $class, 'view', $parent), 403, $authorization->denialMessage($class));

        return new JsonResponse(['fields' => array_map(
            fn (Field $pivot) => [...$pivot->schema(FieldContext::Create, $request), 'value' => $pivot->resolveDefaultValue($request)],
            $this->pivotFields($request, $field, null),
        )]);
    }

    /**
     * The pivot update form of one attached row: the pivot fields with the row's values. Needs view.
     */
    public function pivotFieldValues(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $relationship, string $relatedId): JsonResponse
    {
        [$class, $parent, $field, $relation] = $this->resolve($request, $authorization, $resourceId, $relationship);

        abort_unless($authorization->allows($request->user(), $class, 'view', $parent), 403, $authorization->denialMessage($class));

        [$key, $row] = $this->attachedRow($request, $relation, $relatedId);
        $related = (new Associatable($request, $class, $field))->query()?->option((string) $key);

        return new JsonResponse(['fields' => array_map(
            fn (Field $pivot) => [...$pivot->schema(FieldContext::Update, $request), 'value' => $row[$pivot->attribute()] ?? null],
            $this->pivotFields($request, $field, $related),
        )]);
    }

    /**
     * The parent Resource, its record (404 outside the scope), and the attaching relation field
     * the route names (404 when unknown or not a pivot relation, 403 when hidden).
     *
     * @return array{class-string<\Lathe\Resources\Resource>, Model, BelongsToManyField, BelongsToMany<Model, Model>}
     */
    protected function resolve(LatheRequest $request, ResourceAuthorization $authorization, string $resourceId, string $relationship): array
    {
        $class = $this->viewableResource($request, $authorization);
        $parent = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        $field = $class::make()->fieldsFor(FieldContext::Detail, $request)->first(
            fn (Field $field) => $field instanceof BelongsToManyField && $field->attribute() === $relationship,
        );

        abort_unless($field instanceof BelongsToManyField, 404);
        abort_unless($field->authorizedToSee($request), 403);

        $relation = method_exists($parent, $relationship) ? $parent->{$relationship}() : null;

        abort_unless($field->relatedResource() !== null && $relation instanceof BelongsToMany, 404);

        return [$class, $parent, $field, $relation];
    }

    /**
     * The submitted related_id's record and its key, from the related Resource's relatable query;
     * 422 outside it.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{Model, int|string}
     */
    protected function relatedRecord(LatheRequest $request, string $class, BelongsToManyField $field): array
    {
        Validator::make($request->all(), ['related_id' => ['required']])->validate();

        $id = $request->input('related_id');
        // The picker's own query: the related scopes, the field's relatableQuery and the parent's hook.
        $record = is_int($id) || is_string($id) ? (new Associatable($request, $class, $field))->query()?->option((string) $id) : null;

        if ($record !== null) {
            $key = $record->getKey();

            return [$record, is_int($key) || is_string($key) ? $key : $id];
        }

        throw ValidationException::withMessages([
            'related_id' => Lathe::__('The :attribute must be a valid relation.', ['attribute' => 'related id']),
        ]);
    }

    /**
     * The related key and pivot row the route names: 404 when not attached, 422 when several rows
     * match and no ?pivot_id= picks one.
     *
     * @param  BelongsToMany<Model, Model>  $relation
     * @return array{int|string, array<string, mixed>}
     */
    protected function attachedRow(LatheRequest $request, BelongsToMany $relation, string $relatedId): array
    {
        $pivotId = $request->query('pivot_id');

        if (is_string($pivotId)) {
            $relation->wherePivot($relation->newPivot()->getKeyName(), $pivotId);
        }

        $rows = $relation->newPivotStatementForId($relatedId)->get();

        abort_if($rows->isEmpty(), 404);

        if ($rows->count() > 1) {
            throw ValidationException::withMessages([
                'pivot_id' => Lathe::__('This record is attached more than once. Send the pivot_id of the row to change.'),
            ]);
        }

        /** @var array<string, mixed> $row */
        $row = (array) $rows->first();
        $key = $row[$relation->getRelatedPivotKeyName()] ?? null;

        return [is_int($key) || is_string($key) ? $key : $relatedId, $row];
    }

    /**
     * 403 unless the parent's policy allows the ability for these related keys.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @param  list<int|string>  $ids
     */
    protected function authorizeIds(LatheRequest $request, ResourceAuthorization $authorization, string $class, Model $parent, string $ability, array $ids): void
    {
        abort_unless($authorization->allows($request->user(), $class, $ability, $parent, [$ids]), 403, $authorization->denialMessage($class));
    }

    /**
     * @return list<Field>
     */
    protected function pivotFields(LatheRequest $request, BelongsToManyField $field, ?Model $related): array
    {
        return array_values((new FieldCollection($field->pivotFieldsFor($request, $related)))->authorized($request)->all());
    }

    /**
     * The submitted pivot.<attribute> value of each pivot field.
     *
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    protected function pivotValues(LatheRequest $request, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field->attribute()] = $request->input('pivot.'.$field->attribute());
        }

        return $values;
    }

    /**
     * Validate the pivot fields with their own rules, keyed under pivot.<attribute>.
     *
     * @param  list<Field>  $fields
     * @param  array<string, list<mixed>>  $rules  extra rules
     */
    protected function validatePivot(LatheRequest $request, array $fields, FieldContext $context, array $rules = []): void
    {
        $messages = [];
        $attributes = [];
        $prefix = fn (array $keyed) => array_combine(array_map(fn (int|string $key) => 'pivot.'.$key, array_keys($keyed)), array_values($keyed));

        foreach ($fields as $field) {
            $rules = [...$rules, ...$prefix($field->getRules($context, $request))];
            $messages = [...$messages, ...$prefix($field->getMessages())];
            $attributes = [...$attributes, ...$prefix($field->getValidationAttributes())];
        }

        Validator::make($request->all(), $rules, $messages, $attributes)->validate();
    }
}
