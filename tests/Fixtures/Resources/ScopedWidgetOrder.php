<?php

namespace Lathe\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Lenses\ScopedPendingOrders;
use Lathe\Tests\Fixtures\Widgets\ProgressProbe;
use Lathe\Tests\Fixtures\Widgets\ValueCustomers;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Workbench\App\Lathe\Resources\Order;

/**
 * Orders whose index hides the cancelled ones, with cards over orders and customers.
 */
class ScopedWidgetOrder extends Order
{
    public static function uriKey(): string
    {
        return 'scoped-widget-orders';
    }

    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return parent::configure($definition)->canonical(false);
    }

    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'cancelled');
    }

    public function widgets(LatheRequest $request): array
    {
        return [new ValueOrders, new ValueCustomers, new ProgressProbe];
    }

    public function lenses(LatheRequest $request): array
    {
        return [new ScopedPendingOrders];
    }
}
