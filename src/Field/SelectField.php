<?php

namespace Lathe\Field;

use Illuminate\Validation\Rule;
use Lathe\Field\Contracts\FilterableField;

/**
 * One value out of a list of options.
 */
class SelectField extends Field implements FilterableField
{
    use HasOptions;

    protected string $component = 'select-field';

    /**
     * The submitted value must be one of the options. A select with no options of its own
     * (its list arrives from a dependency) states no list to check against.
     */
    protected function impliedRules(): array
    {
        $values = $this->optionValues();
        $remote = $this->remoteValuesRule();

        return match (true) {
            $values !== [] => [Rule::in($values)],
            $remote !== null => [$remote],
            default => [],
        };
    }

    protected function supportsInline(): bool
    {
        return true;
    }
}
