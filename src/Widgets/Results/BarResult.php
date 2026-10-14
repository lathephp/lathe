<?php

namespace Lathe\Widgets\Results;

use JsonSerializable;

/**
 * A number per group, largest first.
 */
class BarResult implements JsonSerializable
{
    /**
     * @param  array<string, int|float>  $data
     * @param  array<string, string>  $colors
     */
    public function __construct(public array $data, public array $colors = [])
    {
        //
    }

    /**
     * @return array<string, int|float>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
