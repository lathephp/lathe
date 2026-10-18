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
    protected string|int|null $defaultRange = 30;

    public function ranges(): array
    {
        return [7 => '7 Days', 30 => '30 Days', 365 => '365 Days'];
    }

    public function name(): string
    {
        return 'Orders per day';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->countByDays($request, Order::class, null, 'placed_at')->showSumValue();
    }
}
