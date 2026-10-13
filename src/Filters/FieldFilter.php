<?php

namespace Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;

/**
 * The range filter a filterable() field generates: min and max compare the column as stored.
 */
class FieldFilter extends RangeFilter
{
    public function __construct(protected string $label, protected string $attribute, protected int $scale = 1, protected ?Closure $callback = null)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(LatheRequest $request, int $threshold): array
    {
        return [...parent::definition($request, $threshold), 'scale' => $this->scale];
    }

    public function key(): string
    {
        return 'field_'.$this->attribute;
    }

    public function name(): string
    {
        return $this->label;
    }

    /**
     * Whole numbers only when the Resource's model casts the attribute to an integer.
     *
     * @return array{min: int|float|null, max: int|float|null}
     */
    public function normalize(LatheRequest $request, mixed $value, bool $remote): array
    {
        $resource = $request->resource();

        if ($resource !== null && in_array($resource::newModel()->getCasts()[$this->attribute] ?? null, ['int', 'integer'], true)) {
            $this->integers();
        }

        return parent::normalize($request, $value, $remote);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array{min: int|float|null, max: int|float|null}  $value
     * @return Builder<Model>
     */
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        // filterable(fn ...) decides the query itself.
        if ($this->callback !== null) {
            ($this->callback)($request, $query, $value, $this->attribute);

            return $query;
        }

        $column = $query->qualifyColumn($this->attribute);

        return $query
            ->when($value['min'] !== null, fn (Builder $q) => $q->where($column, '>=', $value['min']))
            ->when($value['max'] !== null, fn (Builder $q) => $q->where($column, '<=', $value['max']));
    }
}
