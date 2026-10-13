<?php

namespace Workbench\App\Lathe\Resources;

use Lathe\Field\Condition;
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
        $company = Condition::field('type')->eq('company');

        return [
            Field::text('Name')->required()->inline(),
            Field::email('Email')->required()->rules('email', 'max:255', 'unique:customers,email,{{resourceId}}'),
            Field::country()->default('US')->displayUsingLabels(false),
            Field::select('State')->nullable()->hideFromIndex()->optionsFrom('country', [
                'US' => ['California', 'New York', 'Texas'],
                'BD' => ['Chittagong', 'Dhaka', 'Khulna'],
            ]),
            Field::select('Type')->options(['person' => 'Person', 'company' => 'Company'])->default('person')->hideFromIndex(),
            Field::text('Company name')->hideFromIndex()->visibleWhen($company)->requiredWhen($company),
        ];
    }
}
