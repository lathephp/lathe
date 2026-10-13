<?php

namespace Lathe\Filters;

use Lathe\Http\Requests\LatheRequest;

/**
 * A numeric range: filter[key][min] and filter[key][max], either side may be empty.
 */
abstract class RangeFilter extends Filter
{
    protected bool $integers = false;

    /**
     * Take whole numbers only, for an integer column: PostgreSQL refuses 10.5 or 1e20 there.
     */
    public function integers(bool $integers = true): static
    {
        $this->integers = $integers;

        return $this;
    }

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

        // A saved tab stores the normalized value, and reading the tab normalizes it again.
        if (is_int($side) || (! $this->integers && is_float($side))) {
            return $side;
        }

        if ($this->integers) {
            // filter_var() refuses a leading zero, so the digits are checked first and it only bounds the range.
            $int = is_string($side) && preg_match('/^-?\d+$/', $side) === 1
                ? filter_var(preg_replace('/^(-?)0+(?=\d)/', '$1', $side), FILTER_VALIDATE_INT)
                : false;

            if ($int === false) {
                $this->invalid();
            }

            return $int;
        }

        if (! is_string($side) || ! is_numeric($side)) {
            $this->invalid();
        }

        return $side + 0;
    }
}
