<?php

namespace Lathe\Query;

/**
 * A row count; capped means there are more rows than the value says.
 */
class Count
{
    public function __construct(
        public readonly int $value,
        public readonly bool $capped,
    ) {
        //
    }
}
