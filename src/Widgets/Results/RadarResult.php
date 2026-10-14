<?php

namespace Lathe\Widgets\Results;

use JsonSerializable;

/**
 * Datasets drawn over a ring of axes.
 */
class RadarResult implements JsonSerializable
{
    protected bool $dots = false;

    /** 'polygon' or 'circle' */
    protected string $gridType = 'polygon';

    protected float $fillOpacity = 0.6;

    protected bool $lineOnly = false;

    protected bool $gridNone = false;

    protected bool $radialLines = true;

    protected bool $gridFilled = false;

    protected bool $showLegend = false;

    /** @var list<int>|null */
    protected ?array $polarRadius = null;

    /**
     * @param  list<array{key: string, label: string}>  $axes
     * @param  list<array{name: string, data: list<int|float>}>  $datasets
     */
    public function __construct(public array $axes = [], public array $datasets = [])
    {
        //
    }

    /**
     * Marks each vertex with a dot.
     */
    public function dots(bool $show = true): static
    {
        $this->dots = $show;

        return $this;
    }

    /**
     * The grid shape: 'polygon' or 'circle'.
     */
    public function gridType(string $type): static
    {
        $this->gridType = $type;

        return $this;
    }

    public function fillOpacity(float $opacity): static
    {
        $this->fillOpacity = $opacity;

        return $this;
    }

    /**
     * Draws the outline of each dataset without a fill.
     */
    public function lineOnly(bool $lineOnly = true): static
    {
        $this->lineOnly = $lineOnly;

        return $this;
    }

    /**
     * Hides the grid.
     */
    public function gridNone(bool $none = true): static
    {
        $this->gridNone = $none;

        return $this;
    }

    /**
     * Shows the lines from the centre to each axis.
     */
    public function radialLines(bool $show = true): static
    {
        $this->radialLines = $show;

        return $this;
    }

    /**
     * Fills the grid area with colour.
     */
    public function gridFilled(bool $filled = true): static
    {
        $this->gridFilled = $filled;

        return $this;
    }

    public function showLegend(bool $show = true): static
    {
        $this->showLegend = $show;

        return $this;
    }

    /**
     * The radii of the grid rings, replacing the default rings.
     *
     * @param  list<int>  $radii
     */
    public function polarRadius(array $radii): static
    {
        $this->polarRadius = $radii;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'axes' => $this->axes,
            'datasets' => $this->datasets,
            'dots' => $this->dots,
            'gridType' => $this->gridType,
            'fillOpacity' => $this->fillOpacity,
            'lineOnly' => $this->lineOnly,
            'gridNone' => $this->gridNone,
            'radialLines' => $this->radialLines,
            'gridFilled' => $this->gridFilled,
            'showLegend' => $this->showLegend,
            ...($this->polarRadius === null ? [] : ['polarRadius' => $this->polarRadius]),
        ];
    }
}
