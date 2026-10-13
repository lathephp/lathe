<?php

namespace Lathe\Filters;

use Lathe\Http\Requests\LatheRequest;

/**
 * A numeric range: filter[key][min] and filter[key][max], either side may be empty.
 */
abstract class RangeFilter extends Filter
{
    public function component(): string
    {
        return 'range-filter';
    }

    /**
     * @return array{min: int|float|null, max: int|float|null}
     */
    public function normalize(LatheRequest $request, mixed $value, bool $remote): array
    {
        if (! is_array($value) || array_diff_key($value, ['min' => 0, 'max' => 0]) !== []) {
            $this->invalid();
        }

        return ['min' => $this->number($value['min'] ?? ''), 'max' => $this->number($value['max'] ?? '')];
    }

    protected function number(mixed $side): int|float|null
    {
        if ($side === '') {
            return null;
        }

        if (! is_string($side) || ! is_numeric($side)) {
            $this->invalid();
        }

        return $side + 0;
    }
}
