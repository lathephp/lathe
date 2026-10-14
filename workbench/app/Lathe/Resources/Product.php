<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Lathe\Actions\ImportProducts;
use Workbench\App\Lathe\Actions\MoveToFirst;
use Workbench\App\Models\Product as ProductModel;

class Product extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ProductModel::class)
            ->title('name')
            ->subtitle('sku')
            ->searchable(['name', 'sku'])
            ->icon('Package');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required()->sortable(),
            Field::belongsTo('Category'),
            Field::text('SKU', 'sku')->required()->immutable()->rules('max:16', 'unique:products,sku,{{resourceId}}'),
            Field::currency('Price', 'price_cents')->asMinorUnits()->required()->min(0)->sortable(),
            Field::number('Reorder level')->min(0)->max(10)->step(1)->required(),
            Field::textarea('Description')->onlyOnDetail(),
            Field::repeater('Specifications')->fields([
                Field::text('Name')->required(),
                Field::text('Value')->required(),
            ])->sortable()->confirmRemoval()->addButtonText('Add spec')->rowLabel('name', 'Spec #{{index}}'),
            Field::morphToMany('Tags')->pivotFields([Field::number('Position')->min(0)->max(1000)->step(1)->rules('integer')->required()])->pivotActions(fn () => [MoveToFirst::make()]),
            Field::morphMany('Comments'),
        ];
    }

    public function actions(LatheRequest $request): array
    {
        return [ImportProducts::make()->forResource(static::class)];
    }
}
