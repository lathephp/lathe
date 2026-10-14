<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * Counts the orders of the requested range.
 */
class ValueOrders extends ValueWidget
{
    public function calculate(LatheRequest $request): ValueResult
    {
        return $this->count($request, Order::class);
    }
}
