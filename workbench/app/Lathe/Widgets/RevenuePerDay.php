<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\TrendWidget;
use Workbench\App\Models\Order;

/**
 * The order totals in dollars on each day of the range.
 */
class RevenuePerDay extends TrendWidget
{
    protected string|int|null $defaultRange = 30;

    public function ranges(): array
    {
        return [7 => '7 Days', 30 => '30 Days', 365 => '365 Days'];
    }

    public function name(): string
    {
        return 'Revenue per day';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->sumByDays($request, Order::class, DB::raw('total_cents / 100.0'), 'placed_at')
            ->dollars()
            ->showLatestValue();
    }
}
