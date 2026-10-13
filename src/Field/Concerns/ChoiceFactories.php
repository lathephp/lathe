<?php

namespace Lathe\Field\Concerns;

use Closure;
use Lathe\Field\BooleanField;
use Lathe\Field\BooleanGroupField;
use Lathe\Field\MultiSelectField;
use Lathe\Field\OptionList;
use Lathe\Field\SelectField;
use Lathe\Lathe;

/**
 * The factories of the boolean, select, booleanGroup, multiSelect, country and timezone fields.
 */
trait ChoiceFactories
{
    public static function boolean(string $name, string|Closure|null $attribute = null): BooleanField
    {
        return new BooleanField($name, $attribute);
    }

    public static function select(string $name, string|Closure|null $attribute = null): SelectField
    {
        return new SelectField($name, $attribute);
    }

    public static function booleanGroup(string $name, ?string $attribute = null): BooleanGroupField
    {
        return new BooleanGroupField($name, $attribute);
    }

    public static function multiSelect(string $name, ?string $attribute = null): MultiSelectField
    {
        return new MultiSelectField($name, $attribute);
    }

    /**
     * ISO 3166-1 alpha-2 codes with their English names from ICU (ext-intl).
     */
    public static function country(?string $name = null): SelectField
    {
        return (new SelectField($name ?? Lathe::__('Country')))
            ->options(fn (): array => OptionList::countries())
            ->displayUsingLabels()
            ->searchable();
    }

    public static function timezone(?string $name = null): SelectField
    {
        return (new SelectField($name ?? Lathe::__('Timezone')))
            ->options(fn (): array => OptionList::timezones())
            ->searchable();
    }
}
