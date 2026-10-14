<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PartitionWidget;
use Workbench\App\Models\Customer;

/**
 * How many customers live in each country.
 */
class CustomersByCountry extends PartitionWidget
{
    public function name(): string
    {
        return 'Customers by country';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Customer::class, 'country');
    }
}
