<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * The orders placed in the range, against the range before it.
 */
class Orders extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, null, 'placed_at');
    }
}
