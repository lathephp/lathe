<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Field\FileField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\User as UserModel;

class User extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(UserModel::class)->title('name');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::make('Email'),
            FileField::make('Avatar', 'avatar')->maxSize(5),
        ];
    }
}
