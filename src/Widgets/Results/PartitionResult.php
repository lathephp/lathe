<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;
use Lathe\Widgets\Concerns\RoundingPrecision;
use Lathe\Widgets\PartitionColors;

/**
 * A number per group, largest first, as shares of a whole. jsonSerialize() is the plain data
 * map; payload() is what a partition card sends, with each share and colour.
 */
class PartitionResult implements JsonSerializable
{
    use RoundingPrecision;

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
     * @return array<string, int|float>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @return array{value: list<array{label: string, value: int|float, percentage: int|float, color: string}>}
     */
    public function payload(): array
    {
        $total = array_sum($this->data);
        $colors = PartitionColors::forKeys(array_keys($this->data), $this->colors);
        $segments = [];

        foreach ($this->data as $label => $value) {
            $segments[] = [
                'label' => $this->labelCallback === null ? (string) $label : ($this->labelCallback)((string) $label),
                'value' => $value,
                'percentage' => $total > 0 ? round($value / $total * 100, $this->roundingPrecision, $this->roundingMode) : 0,
                'color' => $colors[$label],
            ];
        }

        return ['value' => $segments];
    }
}
