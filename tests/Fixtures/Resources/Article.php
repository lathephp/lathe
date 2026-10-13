<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Article as ArticleModel;

class Article extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ArticleModel::class)
            ->title('headline')
            ->group('Content')
            ->icon('FileText')
            ->navigationLabel('News');
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
