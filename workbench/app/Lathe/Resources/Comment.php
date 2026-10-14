<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Comment as CommentModel;

class Comment extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(CommentModel::class)
            ->title('body')
            ->searchable(['body'])
            ->icon('MessageSquare');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::morphTo('Commentable')->types([Post::class, Product::class]),
            Field::textarea('Body')->required()->showOnIndex(),
        ];
    }
}
