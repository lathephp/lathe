<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * One page of a relation picker's options, read from the related Resource's scoped query.
 * The parent's fields are built to find the relation field but never resolved.
 */
class AssociatableController
{
    use AuthorizesResources;

    /**
     * The create form's picker: needs create on the parent and runs no query on its table.
     */
    public function create(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $field): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);

        abort_unless($authorization->allows($request->user(), $class, 'create'), 403, $authorization->denialMessage($class));

        return $this->options($request, $class, $this->relationField($request, $class::make()->fieldsFor(FieldContext::Create, $request), $field));
    }

    /**
     * The picker of a saved record (edit form, panels, attach modal): needs view on the record.
     */
    public function record(LatheRequest $request, ResourceAuthorization $authorization, string $resource, string $resourceId, string $field): JsonResponse
    {
        $class = $this->viewableResource($request, $authorization);
        $model = (new ResourceQuery($class::make(), $request, QueryContext::Detail))->find($resourceId);

        abort_unless($authorization->allows($request->user(), $class, 'view', $model), 403, $authorization->denialMessage($class));

        $parent = $class::make($model);
        $fields = $parent->fieldsFor(FieldContext::Update, $request);

        if (! $fields->contains(fn (Field $candidate) => $candidate instanceof RelationField && $candidate->attribute() === $field)) {
            $fields = $parent->fieldsFor(FieldContext::Detail, $request);
        }

        return $this->options($request, $class, $this->relationField($request, $fields, $field));
    }

    /**
     * The relation field the route names: 404 when unknown or not a relation, 403 when hidden.
     */
    protected function relationField(LatheRequest $request, FieldCollection $fields, string $attribute): RelationField
    {
        $field = $fields->first(fn (Field $candidate) => $candidate instanceof RelationField && $candidate->attribute() === $attribute);

        abort_unless($field instanceof RelationField, 404);
        abort_unless($field->authorizedToSee($request), 403);

        return $field;
    }

    /**
     * @param  class-string<resource>  $parent
     */
    protected function options(LatheRequest $request, string $parent, RelationField $field): JsonResponse
    {
        $related = $field->relatedResource();

        abort_if($related === null, 404);

        $resource = $related::make();
        $query = $this->relatedQuery($request, $parent, $field);
        $current = $request->query('current');

        // A saved value asks for its one row; q and page do not apply.
        if ($current !== null) {
            abort_unless(is_string($current) && $current !== '', 404);

            $row = $query->option($current);

            abort_if($row === null, 404);

            $rows = new Collection([$row]);
        } else {
            $rows = $query->options($resource->definition()->getPickerResults(), $field->reordersAssociatables());
        }

        // In debug, a display() Closure reading a column the lookup did not select names it.
        $strict = Model::preventsAccessingMissingAttributes();
        Model::preventAccessingMissingAttributes((bool) config('app.debug'));

        try {
            $items = $rows->map(fn (Model $row) => $field->pickerItem($row))->values()->all();
        } finally {
            Model::preventAccessingMissingAttributes($strict);
        }

        return new JsonResponse(['items' => $items, 'has_more' => $query->hasMorePages()]);
    }

    /**
     * The related Resource's query the options come from: its scopes, the field's
     * relatableQuery, then the parent's relatable{Model}s when it defines one.
     *
     * @param  class-string<resource>  $parent
     */
    protected function relatedQuery(LatheRequest $request, string $parent, RelationField $field): ResourceQuery
    {
        $related = $field->relatedResource();

        abort_if($related === null, 404);

        $query = new ResourceQuery($related::make(), $request, QueryContext::Relatable);
        $method = 'relatable'.Str::plural(class_basename($related::newModel()));

        $query->relatableHooks(
            $field->getRelatableQuery(),
            method_exists($parent, $method) ? function (LatheRequest $request, Builder $builder) use ($parent, $method, $field): Builder {
                /** @var Builder<Model> */
                return $parent::$method($request, $builder, $field);
            } : null,
        );

        if ($field->allowsTrashed() && $request->query('with_trashed') === '1') {
            $query->withTrashed();
        }

        return $query;
    }
}
