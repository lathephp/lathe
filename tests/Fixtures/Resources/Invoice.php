<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Invoice as InvoiceModel;

class Invoice extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(InvoiceModel::class);
    }

    public function fields(LatheRequest $request): array
    {
        return [];
    }
}
