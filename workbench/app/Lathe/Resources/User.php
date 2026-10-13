<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\User as UserModel;

class User extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(UserModel::class)
            ->title('name')
            ->subtitle('email')
            ->searchable(['name'])
            ->globallySearchable();
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::avatar('Avatar', 'avatar')->storeOriginalName('avatar_name')->storeSize('avatar_size')->maxSize(5),
            Field::text('Name')->required()->sortable(),
            Field::email('Email')->required()->rules('email', 'max:255', 'unique:users,email,{{resourceId}}'),
            Field::password()->onlyOnForms()->creationRules('required', 'confirmed')->updateRules('nullable', 'confirmed'),
            Field::passwordConfirmation('Confirm password'),
        ];
    }
}
