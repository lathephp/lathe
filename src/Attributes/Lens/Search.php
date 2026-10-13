<?php

namespace Lathe\Attributes\Lens;

use Attribute;
use Lathe\Search\SearchableColumn;

/**
 * The columns the search box searches on the lens, a bare string as a prefix match.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Search
{
    public function __construct(
        /** @var list<string|SearchableColumn> */
        public array $columns,
    ) {
        //
    }
}
