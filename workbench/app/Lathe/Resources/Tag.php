<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Tag as TagModel;

class Tag extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(TagModel::class)
            ->title('name')
            ->searchable(['name'])
            ->icon('Tag')
            ->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Name')->required(),
        ];
    }
}
