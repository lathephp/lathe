<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Post as PostModel;

class Post extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(PostModel::class)
            ->title('title');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Title'),
            Field::make('Status'),
        ];
    }
}
