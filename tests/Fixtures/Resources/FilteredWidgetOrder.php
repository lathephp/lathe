<?php

namespace Lathe\Tests\Fixtures\Resources;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Lenses\FilteredPendingOrders;
use Lathe\Tests\Fixtures\Widgets\FilteredOrders;
use Workbench\App\Lathe\Resources\Order;

/**
 * Orders with a card that counts the rows the index filters keep.
 */
class FilteredWidgetOrder extends Order
{
    public static function uriKey(): string
    {
        return 'filtered-widget-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public function widgets(LatheRequest $request): array
    {
        return [new FilteredOrders];
    }

    public function lenses(LatheRequest $request): array
    {
        return [new FilteredPendingOrders];
    }
}
