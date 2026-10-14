<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\TrendWidget;
use Workbench\App\Models\Order;

/**
 * The orders placed on each day of the range.
 */
class OrdersPerDay extends TrendWidget
{
    public function name(): string
    {
        return 'Orders per day';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->countByDays($request, Order::class, null, 'placed_at')->showSumValue();
    }
}
