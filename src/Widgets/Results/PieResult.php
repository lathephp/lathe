<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;
use Lathe\Widgets\Concerns\FormatsNumbers;
use Lathe\Widgets\Concerns\RoundingPrecision;
use Lathe\Widgets\PartitionColors;

/**
 * A number per group, largest first, drawn as a pie or a donut, with optional concentric rings.
 */
class PieResult implements JsonSerializable
{
    use FormatsNumbers;
    use RoundingPrecision;

    protected float $innerRadius = 0;

    protected bool $showLabels = false;

    /** @var array<string, array<string, int|float>>|null */
    protected ?array $series = null;

    /** @var (Closure(string): string)|null */
    protected ?Closure $labelCallback = null;

    /**
     * @param  array<string, int|float>  $data
     * @param  array<string, string>  $colors
     */
    public function __construct(public array $data, public array $colors = [])
    {
        //
    }

    /**
     * Changes the label each segment shows.
     *
     * @param  Closure(string): string  $callback
     */
    public function label(Closure $callback): static
    {
        $this->labelCallback = $callback;

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
     * The hole as a share of the radius: 0 is a full pie, above 0 a donut.
     */
    public function innerRadius(float $radius): static
    {
        $this->innerRadius = $radius;

        return $this;
    }

    /**
     * Draws labels outside the segments.
     */
    public function showLabels(bool $show = true): static
    {
        $this->showLabels = $show;

        return $this;
    }

    /**
     * Concentric rings, one per series.
     *
     * @param  array<string, array<string, int|float>>  $series  series name => (label => value)
     */
    public function withSeries(array $series): static
    {
        $this->series = $series;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $series = [];

        foreach ($this->series ?? [] as $name => $data) {
            $series[] = ['name' => (string) $name, 'segments' => $this->segments($data)];
        }

        return [
            'value' => $this->segments($this->data),
            'innerRadius' => $this->innerRadius,
            ...($this->showLabels ? ['showLabels' => true] : []),
            ...($this->series === null ? [] : ['series' => $series]),
            ...$this->numberOptions(),
        ];
    }

    /**
     * @param  array<string, int|float>  $data
     * @return list<array{label: string, value: int|float, percentage: int|float, color: string}>
     */
    protected function segments(array $data): array
    {
        $total = array_sum($data);
        $colors = PartitionColors::forKeys(array_keys($data), $this->colors);
        $segments = [];

        foreach ($data as $label => $value) {
            $segments[] = [
                'label' => $this->labelCallback === null ? (string) $label : ($this->labelCallback)((string) $label),
                'value' => $value,
                'percentage' => $total > 0 ? round($value / $total * 100, $this->roundingPrecision, $this->roundingMode) : 0,
                'color' => $colors[$label],
            ];
        }

        return $segments;
    }
}
