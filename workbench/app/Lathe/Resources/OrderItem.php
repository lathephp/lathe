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
            ->icon('ListOrdered')
            ->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::number('Quantity')->min(1)->required(),
            Field::currency('Price', 'price_cents')->asMinorUnits()->required(),
        ];
    }
}
