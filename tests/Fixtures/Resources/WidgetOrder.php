<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Lenses\CountedOrders;
use Lathe\Tests\Fixtures\Widgets\DetailOnly;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\Secret;
use Workbench\App\Lathe\Resources\Order;

class WidgetOrder extends Order
{
    public static function uriKey(): string
    {
        return 'widget-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function widgets(LatheRequest $request): array
    {
        return [new OrderCount, new Secret, new DetailOnly];
    }

    public function lenses(LatheRequest $request): array
    {
        return [new CountedOrders];
    }
}
