<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ProgressWidget;
use Workbench\App\Lathe\Widgets\Concerns\CountsRangeDays;
use Workbench\App\Models\Order;

/**
 * The shipped orders against all orders placed in the range.
 */
class Shipped extends ProgressWidget
{
    use CountsRangeDays;

    public function calculate(LatheRequest $request): mixed
    {
        /** @var Builder<Model> $placed */
        $placed = Order::where('placed_at', '>=', $this->rangeStart($request));

        // A share of a state, not a goal: it keeps the shipped colour at any percentage.
        return $this->count($request, $placed, fn (Builder $query) => $query->where('status', 'shipped'))
            ->tone('success')
            ->label('of orders placed in the range');
    }
}
