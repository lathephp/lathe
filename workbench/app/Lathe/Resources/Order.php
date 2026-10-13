<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Order as OrderModel;

class Order extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderModel::class)
            ->title('number')
            ->with(['customer'])
            ->icon('ShoppingCart');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Number')->sortable(),
            Field::make('Customer', 'customer.name')->sortable(),
            Field::make('Status'),
            Field::make('Total', 'total_cents', fn (Model $order) => $order instanceof OrderModel
                ? number_format($order->total_cents / 100, 2, '.', '')
                : null)->sortable(),
            Field::make('Placed', 'placed_at', fn (Model $order) => $order instanceof OrderModel
                ? $order->placed_at->utc()->toIso8601ZuluString()
                : null)->sortable(),
        ];
    }
}
