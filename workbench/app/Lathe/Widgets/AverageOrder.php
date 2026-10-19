<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * The average order total of the range in dollars, against the range before it.
 */
class AverageOrder extends ValueWidget
{
    public function name(): string
    {
        return 'Average order';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->average($request, Order::class, 'total_cents', 'placed_at')
            ->transform(fn (int|float $cents) => round($cents / 100, 2))
            ->dollars()
            ->format('.00');
    }
}
