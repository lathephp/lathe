<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\RadialWidget;
use Workbench\App\Models\Order;

/**
 * The shipped orders against all orders.
 */
class OrderCompletion extends RadialWidget
{
    public function name(): string
    {
        return 'Order completion';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'shipped'));
    }
}
