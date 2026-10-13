<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Model;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
use Lathe\Filters\Filter;
use Lathe\Filters\FilterApplicator;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Resources\Resource;

/**
 * The index data the page and the JSON API both send. Built from fields, never from a model.
 */
class IndexPayload
{
    /** @var list<string> */
    public const array ROW_ABILITIES = ['view', 'update', 'delete'];

    public function __construct(
        protected LatheRequest $request,
        protected ResourceAuthorization $authorization,
    ) {
        //
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{resource: array<string, mixed>, table: array<string, mixed>, filters: array{available: list<array<string, mixed>>, applied: array<string, mixed>}, state: array<string, mixed>}
     */
    public function build(string $class): array
    {
        $resource = $class::make();
        $query = new ResourceQuery($resource, $this->request);
        $rows = $query->rows();
        [$perPage, $page] = $query->page();
        $fields = array_values(array_filter($resource->fields($this->request), fn (mixed $field) => $field instanceof Field));
        $softDeletes = $resource->definition()->usesSoftDeletes();
        $abilities = $this->authorization->abilitiesFor(
            $this->request->user(),
            $class,
            $rows,
            $softDeletes ? [...self::ROW_ABILITIES, 'restore', 'forceDelete'] : self::ROW_ABILITIES,
        );
        $threshold = $resource->definition()->getFilterRemoteThreshold();
        $filters = $this->request->query('filter');

        return [
            'resource' => [
                'uriKey' => $class::uriKey(),
                'label' => $class::label(),
                'singularLabel' => $class::singularLabel(),
                'perPageOptions' => $query->perPageOptionsInUse(),
                'definition' => $resource->definition()->options(),
            ],
            'table' => [
                'columns' => $fields,
                'sortKeys' => $query->sortKeys(),
                'rowKey' => 'id',
                'rows' => $rows->map(fn (Model $model) => [
                    'id' => $model->getKey(),
                    'title' => $resource->forModel($model)->title(),
                    'cells' => collect($fields)->mapWithKeys(fn (Field $field) => [$field->key() => $field->cell($model)])->all(),
                    'abilities' => $abilities[$this->key($model)] ?? [],
                    ...($softDeletes ? ['trashed' => method_exists($model, 'trashed') && $model->trashed() === true] : []),
                ])->values()->all(),
            ],
            'filters' => [
                'available' => array_map(fn (Filter $filter) => $this->filter($filter, $threshold), $resource->availableFilters($this->request)),
                'applied' => (new FilterApplicator($resource))->values($this->request, is_array($filters) ? $filters : []),
            ],
            'state' => [
                'page' => $page,
                'perPage' => $perPage,
                'hasMorePages' => $query->hasMorePages(),
                'sort' => $query->sortString(),
            ],
        ];
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{value: int, exact: bool, capped: bool, estimated: bool}|null
     */
    public function count(string $class): ?array
    {
        return (new ResourceQuery($class::make(), $this->request))->count()?->toArray();
    }

    /**
     * A remote filter sends no options, so a long list never loads on the index.
     *
     * @return array<string, mixed>
     */
    protected function filter(Filter $filter, int $threshold): array
    {
        $remote = $filter->isRemote($this->request, $threshold);
        $options = $remote ? null : $filter->options($this->request);

        return [
            'key' => $filter->key(),
            'name' => $filter->name(),
            'component' => $filter->component(),
            'options' => $options === null ? null : array_map(
                fn (string|int $label, mixed $value) => ['label' => (string) $label, 'value' => $value],
                array_keys($options),
                array_values($options),
            ),
            'remote' => $remote,
            'immediate' => $filter->isImmediate(),
            'placement' => $filter->getPlacement(),
            'default' => $filter->default(),
        ];
    }

    protected function key(Model $model): int|string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
    }
}
