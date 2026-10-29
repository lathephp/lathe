<?php

namespace Lathe\Field\Concerns;

/**
 * For a field that keeps a list: an untouched one reaches the server as null (JSON) or as ''
 * (multipart, which ConvertEmptyStringsToNull turns into null), and saves as an empty list.
 *
 * @internal
 */
trait SavesBlankAsEmptyList
{
    /**
     * @internal
     */
    protected function whenBlank(mixed $value): mixed
    {
        return $value === null || $value === '' ? [] : $value;
    }
}
