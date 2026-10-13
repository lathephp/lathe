<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\User as UserModel;

class User extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(UserModel::class)->title('name')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name', 'name'),
            Field::make('Email', 'email'),
        ];
    }
}
