<?php

namespace Lathe\Widgets\Results;

use Closure;
use JsonSerializable;

/**
 * One number, with the number for the period before it and how the client shows both.
 */
class ValueResult implements JsonSerializable
{
    public ?string $previousLabel = null;

    public ?string $prefix = null;

    public ?string $suffix = null;

    public bool $suffixInflection = true;

    /** @var string|array<string, mixed>|null */
    public string|array|null $format = null;

    public ?string $tooltipFormat = null;

    public bool $zeroAllowed = false;

    public bool $copyable = false;

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

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * The client adds an s to it unless the value is 1 or -1, see withoutSuffixInflection().
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function withoutSuffixInflection(): static
    {
        $this->suffixInflection = false;

        return $this;
    }

    /**
     * A compact or fraction digits string ('a', '.2'), or Intl.NumberFormat options.
     *
     * @param  string|array<string, mixed>  $format
     */
    public function format(string|array $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function tooltipFormat(string $format): static
    {
        $this->tooltipFormat = $format;

        return $this;
    }

    public function dollars(string $symbol = '$'): static
    {
        return $this->prefix($symbol);
    }

    public function euros(string $symbol = '€'): static
    {
        return $this->prefix($symbol);
    }

    public function currency(string $symbol = '$'): static
    {
        return $this->prefix($symbol);
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
     * Runs on the value on the server, before the value is rounded.
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
        $value = $this->value;

        if ($value !== null && $this->transformCallback !== null) {
            $value = ($this->transformCallback)($value);
        }

        return [
            'value' => $this->round($value),
            'previous' => $this->round($this->previous),
            'change' => $this->change(),
            'previousLabel' => $this->previousLabel,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'suffixInflection' => $this->suffixInflection,
            'format' => $this->format,
            'tooltipFormat' => $this->tooltipFormat,
            'allowZeroResult' => $this->zeroAllowed,
            'copyable' => $this->copyable,
            'precision' => $this->roundedTo,
            'roundingMode' => $this->roundedBy,
        ];
    }

    private function round(mixed $number): mixed
    {
        if ($this->roundCallback === null || (! is_int($number) && ! is_float($number))) {
            return $number;
        }

        return ($this->roundCallback)($number);
    }
}
