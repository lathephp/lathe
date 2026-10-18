<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\BarWidget;
use Workbench\App\Models\Order;

/**
 * How many of the orders placed in the range are in each status.
 */
class OrdersByStatus extends BarWidget
{
    public function ranges(): array
    {
        return [7 => '7 Days', 30 => '30 Days', 365 => '365 Days'];
    }

    public function defaultRangeKey(): int
    {
        return 30;
    }

    public function name(): string
    {
        return 'Orders by status';
    }

    public function calculate(LatheRequest $request): mixed
    {
        /** @var Builder<Model> $placed */
        $placed = Order::where('placed_at', '>=', now()->subDays((int) $request->query('range', '30')));

        return $this->count($request, $placed, 'status')->label(fn (string $status) => ucfirst($status));
    }
}
