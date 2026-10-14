<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Category as CategoryModel;

class Category extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(CategoryModel::class)
            ->title('name')
            ->searchable(['name'])
            ->icon('Folder');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name'),
        ];
    }
}
