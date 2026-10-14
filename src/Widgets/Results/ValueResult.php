<?php

namespace Lathe\Widgets\Results;

use JsonSerializable;

/**
 * One number, with the number for the period before it.
 */
class ValueResult implements JsonSerializable
{
    public function __construct(
        public int|float|null $value,
        public int|float|null $previous = null,
    ) {
        //
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
     * @return array{value: int|float|null, previous: int|float|null, change: float|null}
     */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'previous' => $this->previous, 'change' => $this->change()];
    }
}
