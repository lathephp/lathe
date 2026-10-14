<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ProgressWidget;
use Workbench\App\Models\Order;

/**
 * The paid orders against all orders.
 */
class PaidOrders extends ProgressWidget
{
    public function name(): string
    {
        return 'Paid orders';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'paid'));
    }
}
