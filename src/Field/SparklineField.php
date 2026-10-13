<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Contracts\PrimesForPage;
use Traversable;
use WeakMap;

/**
 * A small inline chart, 100 px wide and 50 px high unless told otherwise.
 *
 * data() takes one of three sources:
 *  - an iterable of numbers, or an object with a `series(): array` method (a TrendWidget is
 *    one): that one series is drawn on every row;
 *  - a Closure `(Collection $records)` that returns a series per record key, as
 *    `[12 => [1, 3, 2], 13 => [4, 4, 5]]`, or a single flat series for every row. The index
 *    hands it the whole page through primeForPage(), so it runs once and no query runs per row.
 *    Without that call (a detail page) it gets the one record.
 * With no data() the attribute itself holds the series, as a JSON array.
 */
class SparklineField extends Field implements PrimesForPage
{
    protected string $component = 'sparkline-field';

    protected mixed $source = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options += ['height' => 50, 'width' => 100, 'asBarChart' => false];
        $this->exceptOnForms();
    }

    /**
     * @param  Closure|iterable<mixed>|object  $source
     */
    public function data(object|iterable $source): static
    {
        $this->mutate();
        $this->source = $source;

        return $this;
    }

    public function asBarChart(bool $bars = true): static
    {
        return $this->option('asBarChart', $bars);
    }

    public function height(int $pixels): static
    {
        return $this->option('height', $pixels);
    }

    public function width(int $pixels): static
    {
        return $this->option('width', $pixels);
    }

    /**
     * Run the data Closure once for the records of a page. Without a request to hold the
     * answer this does nothing, and each row runs the Closure for itself.
     *
     * @param  Collection<array-key, Model>  $records
     */
    public function primeForPage(Collection $records): void
    {
        $attributes = Evaluator::currentRequest()?->attributes;

        if ($attributes === null || ! $this->source instanceof Closure) {
            return;
        }

        $memo = $attributes->get('lathe.sparklines');

        if (! $memo instanceof WeakMap) {
            $attributes->set('lathe.sparklines', $memo = new WeakMap);
        }

        /** @var WeakMap<Closure, array{0: list<int|float>|null, 1: array<array-key, list<int|float>>}> $memo */
        $memo[$this->source] = self::split(($this->source)($records));
    }

    /**
     * @return array{value: mixed, display: string}
     */
    public function cell(Model $model): array
    {
        if ($this->source === null) {
            return parent::cell($model);
        }

        $series = $this->seriesFor($model);

        return ['value' => $series, 'display' => implode(', ', $series)];
    }

    /**
     * @return list<int|float>
     */
    private function seriesFor(Model $model): array
    {
        $source = $this->source;

        if ($source instanceof Closure) {
            $memo = Evaluator::currentRequest()?->attributes->get('lathe.sparklines');
            /** @var array{0: list<int|float>|null, 1: array<array-key, list<int|float>>} $page */
            $page = $memo instanceof WeakMap && isset($memo[$source])
                ? $memo[$source]
                : self::split($source(new Collection([$model])));

            $key = $model->getKey();

            return $page[1][is_int($key) || is_string($key) ? $key : ''] ?? $page[0] ?? [];
        }

        if (is_object($source) && method_exists($source, 'series')) {
            return self::numbers($source->series());
        }

        return self::numbers($source);
    }

    /**
     * A Closure answer as a shared series and a series per record key.
     *
     * @return array{0: list<int|float>|null, 1: array<array-key, list<int|float>>}
     */
    private static function split(mixed $answer): array
    {
        $answer = is_array($answer) ? $answer : [];

        if (array_filter($answer, is_array(...)) === []) {
            return [self::numbers($answer), []];
        }

        return [null, array_map(self::numbers(...), array_filter($answer, is_array(...)))];
    }

    /**
     * @return list<int|float>
     */
    private static function numbers(mixed $values): array
    {
        $values = $values instanceof Traversable ? iterator_to_array($values, false) : $values;

        return is_array($values) ? array_values(array_filter($values, fn (mixed $number): bool => is_int($number) || is_float($number))) : [];
    }
}
