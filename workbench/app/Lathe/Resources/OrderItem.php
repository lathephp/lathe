<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\OrderItem as OrderItemModel;

class OrderItem extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(OrderItemModel::class)
            ->title('id')
            ->icon('Receipt')
            ->parent(Order::class, 'items');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::belongsTo('Order'),
            Field::belongsTo('Product')->searchable(),
            Field::number('Quantity')->min(1)->required()->sortable(),
            Field::currency('Price', 'price_cents')->asMinorUnits()->required()->hideFromIndex(),
        ];
    }
}
