<?php

namespace Lathe\Field\Relations;

/**
 * A polymorphic many-to-many relation, attached like belongsToMany.
 */
class MorphToManyField extends BelongsToManyField
{
    protected string $component = 'morph-to-many-field';

    public function kind(): string
    {
        return 'morphToMany';
    }
}
