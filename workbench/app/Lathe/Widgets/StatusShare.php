<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\PartitionWidget;
use Workbench\App\Models\Order;

/**
 * The share of the orders placed in the range in each status, in the status colours.
 */
class StatusShare extends PartitionWidget
{
    public function name(): string
    {
        return 'Orders by status';
    }

    public function calculate(LatheRequest $request): mixed
    {
        /** @var Builder<Model> $placed */
        $placed = Order::where('placed_at', '>=', now()->subDays((int) $request->query('range', '30')));
        $result = $this->count($request, $placed, 'status');
        // The statuses in the order an order moves through them, whatever order the query returns.
        $result->data = array_merge(['shipped' => 0, 'paid' => 0, 'pending' => 0, 'cancelled' => 0], $result->data);

        return $result
            ->colors(['shipped' => 'var(--success)', 'paid' => 'var(--info)', 'pending' => 'var(--warning)', 'cancelled' => 'var(--chart-other)'])
            ->label(fn (string $status) => ucfirst($status))
            ->precision(1);
    }
}
