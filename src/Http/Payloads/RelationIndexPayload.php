<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Contracts\PrimesForPage;
use Lathe\Field\Field;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Field\FieldResolver;
use Lathe\Field\Relations\RelationField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * One page of a relation panel: the related Resource's rows read through the parent's relation.
 */
class RelationIndexPayload
{
    public function __construct(
        protected LatheRequest $request,
        protected ResourceAuthorization $authorization,
        protected IndexPayload $index,
    ) {
        //
    }

    /**
     * Each row is an index row plus `related_id`, and `pivot_id` for a pivot relation; the
     * count is capped.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $class  The related Resource.
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array{columns: list<Field>, sortKeys: list<string>, rows: list<array<string, mixed>>, count: array{value: int, exact: bool, capped: bool, estimated: bool}|null, page: int, perPage: int, perPageOptions: non-empty-list<int>, hasMore: bool}
     */
    public function build(string $class, Model $parent, RelationField $field, Relation $relation): array
    {
        $resource = $class::make();
        $query = (new ResourceQuery($resource, $this->request))->forRelation($relation, $field);
        $rows = $query->rows();
        [$perPage, $page] = $query->page();
        $rowKey = $class::rowKey();
        $columns = array_values(array_filter(
            $this->index->columns($resource),
            fn (Field $column) => ! ($column instanceof RelationField && $column->pointsBackAt($parent, $field->attribute())),
        ));
        $pivotFields = array_values((new FieldCollection($query->pivotFields()))->forContext(FieldContext::Index, $this->request)->authorized($this->request)->all());

        foreach ($columns as $column) {
            if ($column instanceof PrimesForPage) {
                $column->primeForPage($rows);
            }
        }

        $softDeletes = $resource->definition()->usesSoftDeletes();
        $abilities = $this->authorization->abilitiesFor(
            $this->request->user(),
            $class,
            $rows,
            $softDeletes ? [...IndexPayload::ROW_ABILITIES, 'restore', 'forceDelete'] : IndexPayload::ROW_ABILITIES,
        );

        return [
            'columns' => [...$columns, ...$pivotFields],
            'sortKeys' => $query->sortKeys(),
            'rows' => array_values($rows->map(fn (Model $model) => [
                'id' => $model->getAttribute($rowKey),
                'title' => $resource->forModel($model)->title(),
                'cells' => [...FieldResolver::cells($model, $columns), ...$this->pivotCells($relation, $model, $pivotFields)],
                'abilities' => $abilities[$this->index->key($model)] ?? [],
                'related_id' => $model->getKey(),
                ...($relation instanceof BelongsToMany ? ['pivot_id' => $model->getAttribute('pivot_id')] : []),
                ...($softDeletes ? ['trashed' => method_exists($model, 'trashed') && $model->trashed() === true] : []),
            ])->all()),
            'count' => $query->count()?->toArray(),
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $query->perPageOptionsInUse(),
            'hasMore' => $query->hasMorePages(),
        ];
    }

    /**
     * The pivot fields' cells, read from the pivot_<attribute> columns the query selected.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @param  list<Field>  $fields
     * @return array<string, array<string, mixed>>
     */
    protected function pivotCells(Relation $relation, Model $model, array $fields): array
    {
        if (! $relation instanceof BelongsToMany || $fields === []) {
            return [];
        }

        $values = [];

        foreach ($fields as $field) {
            $values[$field->attribute()] = $model->getAttribute('pivot_'.$field->attribute());
        }

        return FieldResolver::cells($relation->newExistingPivot($values), $fields);
    }
}
