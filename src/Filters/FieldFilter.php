<?php

namespace Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;

/**
 * The range filter a filterable() field generates: min and max compare the column as stored.
 */
class FieldFilter extends RangeFilter
{
    public function __construct(protected string $label, protected string $attribute)
    {
        //
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
     * @param  Builder<Model>  $query
     * @param  array{min: int|float|null, max: int|float|null}  $value
     * @return Builder<Model>
     */
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        $column = $query->qualifyColumn($this->attribute);

        return $query
            ->when($value['min'] !== null, fn (Builder $q) => $q->where($column, '>=', $value['min']))
            ->when($value['max'] !== null, fn (Builder $q) => $q->where($column, '<=', $value['max']));
    }
}
