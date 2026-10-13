<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Search\LikeSearch;
use Workbench\App\Models\Customer as CustomerModel;

class Customer extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(CustomerModel::class)
            ->title('name')
            ->subtitle('email')
            ->searchable(['name', LikeSearch::contains('email')])
            ->globallySearchable()
            ->icon('Users');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
            Field::make('Email'),
        ];
    }
}
