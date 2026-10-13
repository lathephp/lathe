<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\StringThing as StringThingModel;

class StringThing extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(StringThingModel::class)->title('name')->searchable(['name'])->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
