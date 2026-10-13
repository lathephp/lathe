<?php

namespace Lathe\Field\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\CurrencyField;
use Lathe\Field\NumberField;
use Lathe\Field\TokenField;

/**
 * The factories of the number, currency and token fields.
 */
trait NumberFactories
{
    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     */
    public static function number(string $label, string|Closure|null $attribute = null): NumberField
    {
        return new NumberField($label, $attribute);
    }

    public static function currency(string $label, ?string $attribute = null): CurrencyField
    {
        return new CurrencyField($label, $attribute);
    }

    public static function token(string $label, ?string $attribute = null): TokenField
    {
        return new TokenField($label, $attribute);
    }
}
