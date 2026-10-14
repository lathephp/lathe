<?php

namespace Lathe\Widgets\Concerns;

/**
 * The precision and rounding mode a widget applies to its numbers.
 */
trait RoundingPrecision
{
    protected int $roundingPrecision = 0;

    /** @var int<1, 8> */
    protected int $roundingMode = PHP_ROUND_HALF_UP;

    protected bool $roundingSet = false;

    /**
     * @param  int<1, 8>  $mode  PHP_ROUND_HALF_UP, PHP_ROUND_HALF_EVEN and the other round() modes
     */
    public function precision(int $precision = 0, int $mode = PHP_ROUND_HALF_UP): static
    {
        $this->roundingPrecision = $precision;
        $this->roundingMode = $mode;
        $this->roundingSet = true;

        return $this;
    }

    /**
     * The number rounded to the set precision, or unchanged when none was set.
     */
    public function roundNumber(int|float $value): int|float
    {
        if (! $this->roundingSet) {
            return $value;
        }

        $rounded = round($value, $this->roundingPrecision, $this->roundingMode);

        return $this->roundingPrecision === 0 ? (int) $rounded : $rounded;
    }
}
