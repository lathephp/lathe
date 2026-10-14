<?php

namespace Lathe\Permission\Fields;

/**
 * Reads the names a picker submitted, as an array or its JSON text, without blanks.
 */
trait SyncsNames
{
    /**
     * @return list<string>
     */
    protected static function names(mixed $value): array
    {
        $names = is_string($value) ? json_decode($value, true) : $value;

        return is_array($names) ? array_values(array_filter($names, fn (mixed $name) => is_string($name) && $name !== '')) : [];
    }
}
