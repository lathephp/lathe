<?php

namespace Lathe\Widgets\Results;

use JsonSerializable;

/**
 * A number per bucket, keyed by the bucket's label and in time order.
 */
class TrendResult implements JsonSerializable
{
    /**
     * @param  array<string, int|float>  $data
     */
    public function __construct(public array $data)
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
