<?php

namespace Lathe\Field\Relations;

use Closure;

/**
 * A many-to-many relation listed in a panel on detail, where records are attached and
 * detached with their pivot values.
 */
class BelongsToManyField extends RelationField
{
    use Concerns\HasPivotFields;

    protected string $component = 'belongs-to-many-field';

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->onlyOnDetail();
    }

    public function kind(): string
    {
        return 'belongsToMany';
    }
}
