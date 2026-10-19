<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ProgressWidget;
use Workbench\App\Models\Order;

/**
 * The shipped orders against all orders placed in the range.
 */
class Shipped extends ProgressWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        /** @var Builder<Model> $placed */
        $placed = Order::where('placed_at', '>=', now()->subDays((int) $request->query('range', '30')));

        // A share of a state, not a goal: it keeps the shipped colour at any percentage.
        return $this->count($request, $placed, fn (Builder $query) => $query->where('status', 'shipped'))
            ->tone('success')
            ->label('of orders placed in the range');
    }
}
