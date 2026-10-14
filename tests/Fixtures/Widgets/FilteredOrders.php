<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * Counts the orders the request's filters keep.
 */
class FilteredOrders extends ValueWidget
{
    public function calculate(LatheRequest $request): ValueResult
    {
        return $this->count($request, $this->applyFilters($request, (new Order)->newQuery()));
    }
}
