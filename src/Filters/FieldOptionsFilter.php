<?php

namespace Lathe\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;

/**
 * The select filter a filterable() field generates when it names its options.
 */
class FieldOptionsFilter extends Filter
{
    /**
     * @param  array<array-key, mixed>  $choices  a list of values, or value => label
     */
    public function __construct(protected string $label, protected string $attribute, protected array $choices, protected ?Closure $callback = null)
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
     * @return array<string, mixed> label => value
     */
    public function options(LatheRequest $request): array
    {
        $options = [];

        foreach ($this->choices as $value => $label) {
            [$value, $label] = array_is_list($this->choices) ? [$label, $label] : [$value, $label];
            $options[is_scalar($label) ? (string) $label : ''] = $value;
        }

        return $options;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        if ($this->callback !== null) {
            ($this->callback)($request, $query, $value, $this->attribute);

            return $query;
        }

        return $query->where($query->qualifyColumn($this->attribute), $value);
    }
}
