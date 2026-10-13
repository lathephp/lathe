<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\RepeaterField;

/**
 * The factories of the repeater fields.
 */
trait RepeaterFactories
{
    /**
     * A list of rows kept in a JSON column; hidden from index.
     */
    public static function repeater(string $name, ?string $attribute = null): RepeaterField
    {
        return new RepeaterField($name, $attribute);
    }
}
