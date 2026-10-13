<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\DateField;
use Lathe\Field\DateTimeField;

/**
 * The factories of the date and dateTime fields.
 */
trait DateFactories
{
    public static function date(string $label, ?string $attribute = null): DateField
    {
        return new DateField($label, $attribute);
    }

    public static function dateTime(string $label, ?string $attribute = null): DateTimeField
    {
        return new DateTimeField($label, $attribute);
    }
}
