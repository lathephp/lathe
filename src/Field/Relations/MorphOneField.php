<?php

namespace Lathe\Field\Relations;

/**
 * The single child of a morphOne relation, edited like hasOne.
 */
class MorphOneField extends HasOneField
{
    protected string $component = 'morph-one-field';

    public function kind(): string
    {
        return $this->ofMany ? 'hasOneOfMany' : 'morphOne';
    }
}
