<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Swatch as SwatchModel;

class Swatch extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(SwatchModel::class)->title('colour')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
