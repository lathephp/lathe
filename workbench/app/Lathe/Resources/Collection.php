<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Collection as CollectionModel;

class Collection extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(CollectionModel::class)
            ->group('Catalog')
            ->title('name')
            ->searchable(['name'])
            ->icon('Layers');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
            Field::belongsToMany('Products')->withCount()->pivotFields([Field::number('Position')->min(0)->max(1000)->step(1)->rules('integer')->required()]),
        ];
    }
}
