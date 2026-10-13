<?php

namespace Lathe\Summaries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One total under a column: a sum, an average, a count, a minimum, a maximum or a range.
 *
 * @phpstan-consistent-constructor
 */
class Summary
{
    protected ?string $label = null;

    protected bool $hideLabel = false;

    /** @var (Closure(int|float|string): (string|int|float))|null */
    protected ?Closure $format = null;

    /** @var (Closure(Builder<Model>): mixed)|null */
    protected ?Closure $query = null;

    /**
     * @param  'sum'|'avg'|'count'|'min'|'max'|'range'  $function
     */
    protected function __construct(protected string $function)
    {
        //
    }

    public static function sum(): static
    {
        return new static('sum');
    }

    public static function average(): static
    {
        return new static('avg');
    }

    public static function count(): static
    {
        return new static('count');
    }

    public static function min(): static
    {
        return new static('min');
    }

    public static function max(): static
    {
        return new static('max');
    }

    /**
     * The lowest and the highest value, as "min - max".
     */
    public static function range(): static
    {
        return new static('range');
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function hideLabel(bool $hide = true): static
    {
        $this->hideLabel = $hide;

        return $this;
    }

    /**
     * @param  Closure(int|float|string): (string|int|float)  $format
     */
    public function format(Closure $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Narrow the rows this summary totals. It then runs as its own query, over all records only.
     *
     * @param  Closure(Builder<Model>): mixed  $query
     */
    public function query(Closure $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return (Closure(Builder<Model>): mixed)|null
     */
    public function getQuery(): ?Closure
    {
        return $this->query;
    }

    public function getLabel(): ?string
    {
        if ($this->hideLabel) {
            return null;
        }

        return $this->label ?? match ($this->function) {
            'sum' => 'Sum',
            'avg' => 'Average',
            'count' => 'Count',
            'min' => 'Min',
            'max' => 'Max',
            'range' => 'Range',
        };
    }

    /**
     * The SQL aggregate functions this summary reads: two for a range.
     *
     * @return list<'sum'|'avg'|'count'|'min'|'max'>
     */
    public function functions(): array
    {
        return $this->function === 'range' ? ['min', 'max'] : [$this->function];
    }

    /**
     * The shown value from the result of each function, in the order of functions().
     *
     * @param  list<mixed>  $results
     */
    public function present(array $results): ?string
    {
        $values = array_map($this->value(...), $results);

        if (in_array(null, $values, true)) {
            return null;
        }

        return implode(' - ', $values);
    }

    /**
     * The shown value over in-memory values, with the null handling of SQL aggregates.
     *
     * @param  list<mixed>  $values
     */
    public function presentValues(array $values): ?string
    {
        $values = array_values(array_filter($values, fn (mixed $value) => is_int($value) || is_float($value) || is_numeric($value)));

        return $this->present(array_map(fn (string $function) => match ($function) {
            'count' => count($values),
            default => $values === [] ? null : match ($function) {
                'sum' => array_sum($values),
                'avg' => array_sum($values) / count($values),
                'min' => min($values),
                default => max($values),
            },
        }, $this->functions()));
    }

    protected function value(mixed $result): ?string
    {
        if (! is_int($result) && ! is_float($result) && ! is_numeric($result)) {
            return null;
        }

        // Drivers return "7.50", "7.5" or 7.5 for one value, so every number is read the same way first.
        $number = is_string($result) ? $result + 0 : $result;

        if ($this->format !== null) {
            return (string) ($this->format)($number);
        }

        return is_float($number) ? rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.') : (string) $number;
    }
}
