<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Resources\Customer;

/**
 * Customers that list at most one row in global search.
 */
class SingleResultCustomer extends Customer
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false)->globalSearchResults(1);
    }
}
