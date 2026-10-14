<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Customer;

/**
 * Counts the customers of the requested range.
 */
class ValueCustomers extends ValueWidget
{
    public function calculate(LatheRequest $request): ValueResult
    {
        return $this->count($request, Customer::class);
    }
}
