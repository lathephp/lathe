<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;
use Lathe\Widgets\Concerns\FormatsNumbers;
use Lathe\Widgets\Concerns\RoundingPrecision;
use Lathe\Widgets\PartitionColors;

/**
 * A number per group, largest first. jsonSerialize() is the plain data map; payload() is
 * what a bar card sends, with the bars coloured and the options set on the result.
 */
class BarResult implements JsonSerializable
{
    use FormatsNumbers;
    use RoundingPrecision;

    protected bool $horizontal = false;

    protected bool $stacked = false;

    protected ?string $negativeColor = null;

    /** @var array<string, list<array{label: string, value: int|float}>>|null */
    protected ?array $series = null;

    /** @var (Closure(string): string)|null */
    protected ?Closure $labelCallback = null;

    /** @var (Closure(int|float): mixed)|null */
    protected ?Closure $transformCallback = null;

    /**
     * @param  array<string, int|float>  $data
     * @param  array<string, string>  $colors
     */
    public function __construct(public array $data, public array $colors = [])
    {
        //
    }

    /**
     * Bars grow from left to right.
     */
    public function horizontal(): static
    {
        $this->horizontal = true;

        return $this;
    }

    /**
     * Stacks the series of a multi-series chart instead of grouping them side by side.
     */
    public function stacked(): static
    {
        $this->stacked = true;

        return $this;
    }

    /**
     * The colour of bars below zero.
     */
    public function negativeColor(string $color): static
    {
        $this->negativeColor = $color;

        return $this;
    }

    /**
     * @param  array<string, string>  $colors  keyed by label
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * Changes the label each bar shows.
     *
     * @param  Closure(string): string  $callback
     */
    public function label(Closure $callback): static
    {
        $this->labelCallback = $callback;

        return $this;
    }

    /**
     * Changes the value each bar shows.
     *
     * @param  callable(int|float): mixed  $callback
     */
    public function transform(callable $callback): static
    {
        $this->transformCallback = $callback(...);

        return $this;
    }

    /**
     * Several series for a grouped or stacked chart.
     *
     * @param  array<string, array<string, int|float>>  $series  series name => (label => value)
     */
    public function withSeries(array $series): static
    {
        $this->series = [];

        foreach ($series as $name => $points) {
            $this->series[$name] = [];

            foreach ($points as $label => $value) {
                $this->series[$name][] = ['label' => (string) $label, 'value' => $value];
            }
        }

        return $this;
    }

    /**
     * @return array<string, int|float>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $colors = PartitionColors::forKeys(array_keys($this->data), $this->colors);
        $bars = [];

        foreach ($this->data as $label => $value) {
            $shown = $this->transformCallback === null ? $value : ($this->transformCallback)($value);

            $bars[] = [
                'label' => $this->labelCallback === null ? (string) $label : ($this->labelCallback)((string) $label),
                'value' => is_int($shown) || is_float($shown) ? $this->roundNumber($shown) : $shown,
                'color' => $colors[$label],
            ];
        }

        return [
            'bars' => $bars,
            ...($this->horizontal ? ['horizontal' => true] : []),
            ...($this->stacked ? ['stacked' => true] : []),
            ...($this->series === null ? [] : ['series' => $this->series]),
            ...($this->negativeColor === null ? [] : ['negativeColor' => $this->negativeColor]),
            ...$this->numberOptions(),
        ];
    }
}
