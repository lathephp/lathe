<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * The order totals of the range in dollars, against the range before it.
 */
class Revenue extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->sum($request, Order::class, 'total_cents', 'placed_at')
            ->transform(fn (int|float $cents) => round($cents / 100, 2))
            ->dollars();
    }
}
