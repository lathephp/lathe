<?php

namespace Lathe\Field\Relations;

/**
 * The children of a morphMany relation, listed and created like hasMany.
 */
class MorphManyField extends HasManyField
{
    protected string $component = 'morph-many-field';

    public function kind(): string
    {
        return 'morphMany';
    }
}
