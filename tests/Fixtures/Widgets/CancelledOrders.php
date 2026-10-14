<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * Counts the cancelled orders of the last 30 days.
 */
class CancelledOrders extends ValueWidget
{
    public function calculate(LatheRequest $request): ValueResult
    {
        return $this->aggregateQuery(Order::class)->query(fn (Builder $query) => $query->where('status', 'cancelled'))->value();
    }
}
