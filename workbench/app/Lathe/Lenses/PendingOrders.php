<?php

namespace Workbench\App\Lathe\Lenses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;
use Lathe\Summaries\Summary;
use Workbench\App\Lathe\Filters\OrderCustomer;
use Workbench\App\Lathe\Filters\OrderStatus;
use Workbench\App\Models\Order;

class PendingOrders extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number'),
            Field::make('Customer', 'customer.name'),
            // The order's own status, edited in place like on Orders; a paid order leaves the lens on the next load.
            Field::select('Status')
                ->options(['pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'])
                ->badges(['pending' => 'warning', 'paid' => 'info', 'shipped' => 'success', 'cancelled' => 'neutral'])
                ->meter(except: ['cancelled'])
                ->inline(),
            Field::make('Total', 'total_cents', fn (Model $order) => $order instanceof Order
                ? number_format($order->total_cents / 100, 2, '.', '')
                : null)->sortable()->summarize(Summary::sum()->label('Total')->format(fn (int|float|string $cents) => number_format((float) $cents / 100, 2))),
            Field::make('Placed', 'placed_at', fn (Model $order) => $order instanceof Order
                ? $order->placed_at->utc()->toIso8601ZuluString()
                : null)->sortable(),
        ];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), 'pending');
    }

    public function filters(LatheRequest $request): array
    {
        return [(new OrderStatus)->multiple(), new OrderCustomer];
    }
}
