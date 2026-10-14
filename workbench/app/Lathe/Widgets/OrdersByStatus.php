<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\BarWidget;
use Workbench\App\Models\Order;

/**
 * How many orders are in each status.
 */
class OrdersByStatus extends BarWidget
{
    public function name(): string
    {
        return 'Orders by status';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, 'status')->label(fn (string $status) => ucfirst($status));
    }
}
