<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\UuidThing as UuidThingModel;

class UuidThing extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(UuidThingModel::class)->title('name')->searchable(['name'])->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
