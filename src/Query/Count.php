<?php

namespace Lathe\Query;

/**
 * A row count. Capped means there are more rows than the value says; estimated means
 * the value is the database's guess for the whole table.
 */
class Count
{
    public function __construct(
        public readonly int $value,
        public readonly bool $capped,
        public readonly bool $exact = false,
        public readonly bool $estimated = false,
    ) {
        //
    }

    /**
     * @return array{value: int, exact: bool, capped: bool, estimated: bool}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'exact' => $this->exact, 'capped' => $this->capped, 'estimated' => $this->estimated];
    }
}
