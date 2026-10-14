<?php

namespace Lathe\Field\Relations;

/**
 * The inverse of a morphToMany relation, attached like belongsToMany.
 */
class MorphedByManyField extends BelongsToManyField
{
    protected string $component = 'morphed-by-many-field';

    public function kind(): string
    {
        return 'morphedByMany';
    }
}
