<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\UlidThing as UlidThingModel;

class UlidThing extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(UlidThingModel::class)->title('name')->searchable(['name'])->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
