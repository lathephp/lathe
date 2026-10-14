<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Workbench\App\Lathe\Resources\Customer;

/**
 * Customers with a widget; the Customer policy has no viewWidget method.
 */
class WidgetCustomer extends Customer
{
    public static function uriKey(): string
    {
        return 'widget-customers';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function widgets(LatheRequest $request): array
    {
        return [new OrderCount];
    }
}
