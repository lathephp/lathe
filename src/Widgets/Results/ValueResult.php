<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;
use Lathe\Widgets\Concerns\FormatsNumbers;

/**
 * One number, with the number for the period before it and how the client shows both.
 */
class ValueResult implements JsonSerializable
{
    use FormatsNumbers;

    public ?string $previousLabel = null;

    public ?string $tooltipFormat = null;

    public bool $zeroAllowed = false;

    public bool $copyable = false;

    public bool $lowerIsBetter = false;

    protected ?Closure $transformCallback = null;

    /** @var (Closure(int|float): (int|float))|null */
    protected ?Closure $roundCallback = null;

    protected ?int $roundedTo = null;

    protected ?int $roundedBy = null;

    public function __construct(
        public int|float|null $value,
        public int|float|null $previous = null,
    ) {
        //
    }

    public function previous(int|float|null $previous, ?string $label = null): static
    {
        $this->previous = $previous;
        $this->previousLabel = $label;

        return $this;
    }

    public function tooltipFormat(string $format): static
    {
        $this->tooltipFormat = $format;

        return $this;
    }

    /**
     * Shows a value of 0 as 0, where the client otherwise shows "No Data".
     */
    public function allowZeroResult(bool $allow = true): static
    {
        $this->zeroAllowed = $allow;

        return $this;
    }

    public function copyable(): static
    {
        $this->copyable = true;

        return $this;
    }

    /**
     * Marks a rise as bad, such as cancelled orders: a rise shows in the destructive colour
     * and a fall in the success colour.
     */
    public function lowerIsBetter(bool $lowerIsBetter = true): static
    {
        $this->lowerIsBetter = $lowerIsBetter;

        return $this;
    }

    /**
     * Runs on the value and the previous value on the server, before they are rounded.
     *
     * @param  callable(int|float): mixed  $callback
     */
    public function transform(callable $callback): static
    {
        $this->transformCallback = $callback(...);

        return $this;
    }

    /**
     * Rounds the value and the previous value when the result is sent, and says how in the payload.
     *
     * @param  Closure(int|float): (int|float)  $round
     */
    public function rounded(Closure $round, int $precision, int $mode): static
    {
        $this->roundCallback = $round;
        $this->roundedTo = $precision;
        $this->roundedBy = $mode;

        return $this;
    }

    /**
     * The change from the previous number as a percentage, null when there is nothing to compare with.
     */
    public function change(): ?float
    {
        if ($this->value === null || $this->previous === null || $this->previous == 0) {
            return null;
        }

        return round(($this->value - $this->previous) / $this->previous * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->round($this->transformed($this->value)),
            'previous' => $this->round($this->transformed($this->previous)),
            'change' => $this->change(),
            'previousLabel' => $this->previousLabel,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'suffixInflection' => $this->suffixInflection,
            'format' => $this->format,
            'tooltipFormat' => $this->tooltipFormat,
            'allowZeroResult' => $this->zeroAllowed,
            'copyable' => $this->copyable,
            'lowerIsBetter' => $this->lowerIsBetter,
            'precision' => $this->roundedTo,
            'roundingMode' => $this->roundedBy,
        ];
    }

    /**
     * The previous number goes through the transform too, so both are shown in one unit.
     */
    private function transformed(int|float|null $number): mixed
    {
        return $number === null || $this->transformCallback === null ? $number : ($this->transformCallback)($number);
    }

    private function round(mixed $number): mixed
    {
        if ($this->roundCallback === null || (! is_int($number) && ! is_float($number))) {
            return $number;
        }

        return ($this->roundCallback)($number);
    }
}
