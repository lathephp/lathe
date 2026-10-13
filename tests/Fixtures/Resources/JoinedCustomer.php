<?php

namespace Lathe\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Customer;

/**
 * Customers joined to a customer_notes table that also has id, name and status columns.
 */
class JoinedCustomer extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(Customer::class)->canonical(false)->title('name')->searchable(['name'])->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->select('customers.*')->join('customer_notes', 'customer_notes.customer_id', '=', 'customers.id');
    }
}
