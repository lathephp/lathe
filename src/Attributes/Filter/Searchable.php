<?php

namespace Lathe\Attributes\Filter;

use Attribute;

/**
 * Serve the filter's options as a remote picker, like ->searchable().
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Searchable
{
    //
}
