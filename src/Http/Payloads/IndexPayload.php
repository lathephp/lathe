<?php

namespace Lathe\Http\Payloads;

use Illuminate\Database\Eloquent\Model;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Field\Field;
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
     * @return array{resource: array<string, mixed>, table: array<string, mixed>, state: array<string, mixed>}
     */
    public function build(string $class): array
    {
        $resource = $class::make();
        $query = new ResourceQuery($resource, $this->request);
        $rows = $query->rows();
        [$perPage, $page] = $query->page();
        $fields = array_values(array_filter($resource->fields($this->request), fn (mixed $field) => $field instanceof Field));
        $abilities = $this->authorization->abilitiesFor($this->request->user(), $class, $rows, self::ROW_ABILITIES);

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
                'rowKey' => 'id',
                'rows' => $rows->map(fn (Model $model) => [
                    'id' => $model->getKey(),
                    'title' => $resource->forModel($model)->title(),
                    'cells' => collect($fields)->mapWithKeys(fn (Field $field) => [$field->key() => $field->cell($model)])->all(),
                    'abilities' => $abilities[$this->key($model)] ?? [],
                ])->values()->all(),
            ],
            'state' => [
                'page' => $page,
                'perPage' => $perPage,
                'hasMorePages' => $query->hasMorePages(),
            ],
        ];
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     * @return array{count: int, capped: bool}
     */
    public function count(string $class): array
    {
        $count = (new ResourceQuery($class::make(), $this->request))->count();

        return ['count' => $count->value, 'capped' => $count->capped];
    }

    protected function key(Model $model): int|string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : (string) json_encode($key);
    }
}
