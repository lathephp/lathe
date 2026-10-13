<?php

namespace Lathe\Field\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A field that reads its value for a whole page of records at once, before the rows resolve,
 * so a Closure or a query runs once per page and never per row.
 */
interface PrimesForPage
{
    /**
     * @param  Collection<array-key, Model>  $records
     */
    public function primeForPage(Collection $records): void;
}
