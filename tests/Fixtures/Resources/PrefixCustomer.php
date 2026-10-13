<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Customer;

/**
 * Customers with every column searched by prefix and tight term limits.
 */
class PrefixCustomer extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)
            ->canonical(false)
            ->title('name')
            ->searchable(['name', 'email'])
            ->maxPrimaryKeySize(99)
            ->searchTermChars(50)
            ->searchTermWords(3)
            ->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
