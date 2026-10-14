<?php

namespace Lathe\Widgets;

use JsonSerializable;

/**
 * How a chart card's hover tooltip looks. Attach it with Widget::tooltip().
 */
class Tooltip implements JsonSerializable
{
    protected string $indicator = 'dot';

    protected bool $hideLabel = false;

    protected bool $hideIndicator = false;

    protected ?string $label = null;

    protected ?string $valuePrefix = null;

    protected ?string $valueSuffix = null;

    protected bool $showTotal = false;

    public static function make(): static
    {
        return new static;
    }

    /**
     * The marker beside each value: 'dot', 'line' or 'dashed'.
     */
    public function indicator(string $indicator): static
    {
        $this->indicator = $indicator;

        return $this;
    }

    public function hideLabel(bool $hide = true): static
    {
        $this->hideLabel = $hide;

        return $this;
    }

    public function hideIndicator(bool $hide = true): static
    {
        $this->hideIndicator = $hide;

        return $this;
    }

    /**
     * Replaces the header text of the tooltip.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function valuePrefix(string $prefix): static
    {
        $this->valuePrefix = $prefix;

        return $this;
    }

    public function valueSuffix(string $suffix): static
    {
        $this->valueSuffix = $suffix;

        return $this;
    }

    /**
     * Adds a total row at the bottom, for stacked charts.
     */
    public function showTotal(bool $show = true): static
    {
        $this->showTotal = $show;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'indicator' => $this->indicator,
            'hideLabel' => $this->hideLabel ?: null,
            'hideIndicator' => $this->hideIndicator ?: null,
            'label' => $this->label,
            'valuePrefix' => $this->valuePrefix,
            'valueSuffix' => $this->valueSuffix,
            'showTotal' => $this->showTotal ?: null,
        ], fn (mixed $value) => $value !== null);
    }
}
