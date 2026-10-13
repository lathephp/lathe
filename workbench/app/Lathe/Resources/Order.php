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
            Field::make('Number'),
            Field::make('Customer', 'customer.name'),
            Field::make('Status'),
            Field::make('Total', 'total', fn (Model $order) => $order instanceof OrderModel
                ? number_format($order->total_cents / 100, 2, '.', '')
                : null),
            Field::make('Placed', 'placed_at', fn (Model $order) => $order instanceof OrderModel
                ? $order->placed_at->utc()->toIso8601ZuluString()
                : null),
        ];
    }
}
