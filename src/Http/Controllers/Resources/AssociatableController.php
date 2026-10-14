<?php

namespace Lathe\Http\Controllers\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\MultiSelectField;
use Lathe\Field\Relations\Associatable;
use Lathe\Field\Relations\RelationField;
use Lathe\Field\SelectField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * One page of a relation picker's options, read from the related Resource's scoped query,
 * or of a select's remote options. The parent's fields are built to find the field but never
 * resolved.
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

        return $this->answer($request, $class, $this->pickerField($request, $class::make()->fieldsFor(FieldContext::Create, $request), $field));
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

        if (! $fields->contains(fn (Field $candidate) => $this->isPicker($candidate, $field))) {
            $fields = $parent->fieldsFor(FieldContext::Detail, $request);
        }

        return $this->answer($request, $class, $this->pickerField($request, $fields, $field), $model);
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
     * The picker field the route names: 404 when unknown or no picker, 403 when hidden.
     */
    protected function pickerField(LatheRequest $request, FieldCollection $fields, string $attribute): RelationField|SelectField|MultiSelectField
    {
        $field = $fields->first(fn (Field $candidate) => $this->isPicker($candidate, $attribute));

        abort_unless($field instanceof RelationField || $field instanceof SelectField || $field instanceof MultiSelectField, 404);
        abort_unless($field->authorizedToSee($request), 403);

        return $field;
    }

    /**
     * A relation field, or a select whose options the server searches.
     */
    protected function isPicker(Field $field, string $attribute): bool
    {
        $picker = $field instanceof RelationField || (($field instanceof SelectField || $field instanceof MultiSelectField) && $field->remoteSource() !== null);

        return $picker && $field->attribute() === $attribute;
    }

    /**
     * @param  class-string<resource>  $parent
     */
    protected function answer(LatheRequest $request, string $parent, RelationField|SelectField|MultiSelectField $field, ?Model $record = null): JsonResponse
    {
        return $field instanceof RelationField ? $this->options($request, $parent, $field, $record) : $this->remoteOptions($request, $parent, $field);
    }

    /**
     * One page of the values that contain q, ordered, or the one saved value `current` names.
     *
     * @param  class-string<resource>  $parent
     */
    protected function remoteOptions(LatheRequest $request, string $parent, SelectField|MultiSelectField $field): JsonResponse
    {
        $remote = $field->remoteSource();

        abort_if($remote === null, 404);

        $query = $field->remoteQuery($request);
        $current = $request->query('current');

        if ($current !== null) {
            abort_unless(is_string($current) && $current !== '', 404);

            $items = $remote->find($query, $current);

            abort_if($items === null, 404);

            return new JsonResponse(['items' => $items, 'has_more' => false]);
        }

        $term = $request->query('q');

        return new JsonResponse($remote->search($query, is_string($term) ? trim($term) : '', $parent::make()->definition()->getPickerResults()));
    }

    /**
     * @param  class-string<resource>  $parent
     */
    protected function options(LatheRequest $request, string $parent, RelationField $field, ?Model $record = null): JsonResponse
    {
        $type = $request->query('type');
        // A morphTo picker reads the type as a uriKey among its types(); anything else is 404.
        $associatable = (new Associatable($request, $parent, $field))->forType(is_string($type) ? $type : null);
        $related = $associatable->relatedResource();

        abort_if($related === null, 404);

        $resource = $related::make();
        $query = $associatable->query();

        abort_if($query === null, 404);

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

        // The attach modal offers only the records the policy lets the user attach: one call with the page's ids.
        if ($field->attaches() && $record !== null) {
            $keys = array_values(array_filter($rows->map(fn (Model $row) => $row->getKey())->all(), fn (mixed $key) => is_int($key) || is_string($key)));
            $allowed = app(ResourceAuthorization::class)->allowedIds($request->user(), $parent, 'attach'.Str::studly($field->attribute()), $record, $keys);
            $rows = $rows->filter(fn (Model $row) => in_array($row->getKey(), $allowed, true))->values();
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
}
