<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;
use Lathe\Lathe;
use Lathe\Widgets\Concerns\RoundingPrecision;
use Lathe\Widgets\PartitionColors;

/**
 * A value against a target, drawn as a gauge, as rings or as one stacked ring. The gauge
 * colour follows the progress: destructive below 33%, warning from 33% to under 66%, success
 * from 66%. avoid() reverses it for a number that should stay low.
 */
class RadialResult implements JsonSerializable
{
    use RoundingPrecision;

    protected int $startAngle = 0;

    protected int $endAngle = 360;

    protected bool $showTrack = true;

    protected bool $avoid = false;

    protected ?string $color = null;

    protected ?string $label = null;

    protected bool $showLabels = false;

    protected bool $showGrid = false;

    /** @var list<array{label: string, value: int|float, target?: int|float, color?: string}>|null */
    protected ?array $segments = null;

    /** @var list<array{name: string, value: int|float}>|null */
    protected ?array $stacked = null;

    /** @var (Closure(int|float): mixed)|null */
    protected ?Closure $transformCallback = null;

    public function __construct(public int|float $value = 0, public int|float $target = 100)
    {
        $this->roundingPrecision = 2;
    }

    public function startAngle(int $angle): static
    {
        $this->startAngle = $angle;

        return $this;
    }

    public function endAngle(int $angle): static
    {
        $this->endAngle = $angle;

        return $this;
    }

    /**
     * Shows the background ring behind the value.
     */
    public function showTrack(bool $show = true): static
    {
        $this->showTrack = $show;

        return $this;
    }

    /**
     * Marks a number that should stay low: the colours reverse and the caption reads "Lower is better".
     */
    public function avoid(): static
    {
        $this->avoid = true;

        return $this;
    }

    /**
     * A fixed colour, replacing the progress colours.
     */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * The text under the value.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Changes the value the gauge shows; the share still uses the real value.
     *
     * @param  callable(int|float): mixed  $callback
     */
    public function transform(callable $callback): static
    {
        $this->transformCallback = $callback(...);

        return $this;
    }

    /**
     * One ring per segment.
     *
     * @param  list<array{label: string, value: int|float, target?: int|float, color?: string}>  $segments
     */
    public function segments(array $segments): static
    {
        $this->segments = $segments;

        return $this;
    }

    /**
     * Shows the label inside each ring.
     */
    public function showLabels(bool $show = true): static
    {
        $this->showLabels = $show;

        return $this;
    }

    /**
     * Shows a circle grid behind the rings.
     */
    public function showGrid(bool $show = true): static
    {
        $this->showGrid = $show;

        return $this;
    }

    /**
     * Several items sharing one ring.
     *
     * @param  list<array{name: string, value: int|float}>  $items
     */
    public function stacked(array $items): static
    {
        $this->stacked = $items;

        return $this;
    }

    /**
     * The value as a share of the target, 0 when there is no target.
     */
    public function percentage(): float
    {
        return $this->target == 0 ? 0.0 : round($this->value / $this->target * 100, $this->roundingPrecision, $this->roundingMode);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $shown = $this->transformCallback === null ? $this->value : ($this->transformCallback)($this->value);
        $percentage = $this->percentage();

        return [
            'value' => is_float($shown) ? round($shown, $this->roundingPrecision, $this->roundingMode) : $shown,
            'target' => max($this->value, $this->target),
            'percentage' => $percentage,
            'startAngle' => $this->startAngle,
            'endAngle' => $this->endAngle,
            'showTrack' => $this->showTrack,
            'showLabels' => $this->showLabels,
            'showGrid' => $this->showGrid,
            'color' => $this->color ?? $this->progressColor($percentage),
            ...($this->avoid ? ['caption' => Lathe::__('Lower is better')] : []),
            ...($this->label === null ? [] : ['label' => $this->label]),
            ...($this->segments === null ? [] : ['segments' => $this->segmentList($this->segments)]),
            ...($this->stacked === null ? [] : ['stacked' => $this->stacked]),
        ];
    }

    protected function progressColor(float $percentage): string
    {
        if ($percentage >= 33 && $percentage < 66) {
            return 'var(--warning)';
        }

        // Low progress is bad and high progress is good, until avoid() swaps the two.
        return ($percentage < 33) !== $this->avoid ? 'var(--destructive)' : 'var(--success)';
    }

    /**
     * @param  list<array{label: string, value: int|float, target?: int|float, color?: string}>  $segments
     * @return list<array{label: string, value: int|float, color: string, target?: int|float, percentage?: int|float}>
     */
    protected function segmentList(array $segments): array
    {
        $own = [];

        foreach ($segments as $segment) {
            if (isset($segment['color'])) {
                $own[$segment['label']] = $segment['color'];
            }
        }

        $colors = PartitionColors::forKeys(array_column($segments, 'label'), $own);

        return array_map(function (array $segment) use ($colors) {
            $sent = ['label' => $segment['label'], 'value' => $segment['value'], 'color' => $colors[$segment['label']]];

            if (isset($segment['target'])) {
                $sent['target'] = $segment['target'];
                $sent['percentage'] = $segment['target'] > 0
                    ? round($segment['value'] / $segment['target'] * 100, $this->roundingPrecision, $this->roundingMode)
                    : 0;
            }

            return $sent;
        }, $segments);
    }
}
