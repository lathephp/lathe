<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tabs\Tab;
use Workbench\App\Lathe\Filters\OrderCustomer;
use Workbench\App\Lathe\Filters\OrderStatus;
use Workbench\App\Models\Order as OrderModel;

class Order extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)
            ->title('number')
            ->searchable(['number'])
            ->with(['customer'])
            ->icon('ShoppingCart')
            ->savedTabs();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number')->sortable(),
            Field::make('Customer', 'customer.name')->sortable(),
            Field::make('Status'),
            Field::make('Total', 'total_cents', fn (Model $order) => $order instanceof OrderModel
                ? number_format($order->total_cents / 100, 2, '.', '')
                : null)->sortable()->filterable(),
            Field::make('Placed', 'placed_at', fn (Model $order) => $order instanceof OrderModel
                ? $order->placed_at->utc()->toIso8601ZuluString()
                : null)->sortable(),
        ];
    }

    public function filters(LatheRequest $request): array
    {
        return [new OrderStatus, new OrderCustomer];
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
