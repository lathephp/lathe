<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\SearchableRelation;
use Lathe\Summaries\Summary;
use Lathe\Tabs\Tab;
use Workbench\App\Lathe\Filters\OrderCustomer;
use Workbench\App\Lathe\Filters\OrderStatus;
use Workbench\App\Lathe\Lenses\PendingOrders;
use Workbench\App\Models\Order as OrderModel;

class Order extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)
            ->title('number')
            ->searchable(['number', new SearchableRelation('customer', 'name')])
            ->globallySearchable()
            ->with(['customer'])
            ->icon('ShoppingCart')
            ->savedTabs()
            ->polling(10)
            ->showPollingToggle();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number')->sortable(),
            Field::make('Customer', 'customer.name')->sortable(),
            Field::make('Status'),
            Field::make('Total', 'total_cents', fn (Model $order) => $order instanceof OrderModel
                ? number_format($order->total_cents / 100, 2, '.', '')
                : null)->sortable()->filterable()
                ->summarize(Summary::sum()->label('Revenue')->format(fn (int|float|string $cents) => number_format((float) $cents / 100, 2, '.', ''))),
            Field::make('Placed', 'placed_at', fn (Model $order) => $order instanceof OrderModel
                ? $order->placed_at->utc()->toIso8601ZuluString()
                : null)->sortable(),
        ];
    }

    public function filters(LatheRequest $request): array
    {
        return [new OrderStatus, new OrderCustomer];
    }

    public function lenses(LatheRequest $request): array
    {
        return [new PendingOrders];
    }

    public function tabs(LatheRequest $request): array
    {
        return [
            Tab::make('All'),
            Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where($query->qualifyColumn('status'), 'pending'))
                ->withCount(),
            Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $query) => $query->where($query->qualifyColumn('status'), 'paid')),
        ];
    }
}
