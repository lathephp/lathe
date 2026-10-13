<?php

namespace Lathe\Attributes\Lens;

use Attribute;

/**
 * The lens page size options; the first is the default.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class PerPage
{
    public function __construct(
        /** @var list<int> */
        public array $options,
    ) {
        //
    }
}
