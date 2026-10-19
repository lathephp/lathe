<?php

namespace Workbench\App\Lathe\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Lathe\Widgets\AverageOrder;
use Workbench\App\Lathe\Widgets\BiggestSpenders;
use Workbench\App\Lathe\Widgets\CategorySales;
use Workbench\App\Lathe\Widgets\OrderingCountries;
use Workbench\App\Lathe\Widgets\Orders;
use Workbench\App\Lathe\Widgets\OrdersByWeekday;
use Workbench\App\Lathe\Widgets\OrdersPerDay;
use Workbench\App\Lathe\Widgets\PaidOrders;
use Workbench\App\Lathe\Widgets\Revenue;
use Workbench\App\Lathe\Widgets\RevenuePerDay;
use Workbench\App\Lathe\Widgets\Shipped;
use Workbench\App\Lathe\Widgets\StatusShare;

/**
 * The workbench home page.
 */
class Sales extends Dashboard
{
    public static function uriKey(): string
    {
        return 'main';
    }

    public function widgets(LatheRequest $request): array
    {
        return [
            Revenue::make()->width(CardWidth::Quarter),
            Orders::make()->width(CardWidth::Quarter),
            AverageOrder::make()->width(CardWidth::Quarter),
            Shipped::make()->width(CardWidth::Quarter),
            RevenuePerDay::make()->showAxes()->curveType('linear')->width(CardWidth::TwoThirds),
            StatusShare::make()->width(CardWidth::Third),
            BiggestSpenders::make()->width(CardWidth::Half),
            OrderingCountries::make()->width(CardWidth::Half),
            CategorySales::make()->width(CardWidth::Half),
            OrdersByWeekday::make()->width(CardWidth::Half),
            // Cards from before the layout above; a user can hide them in Customize.
            new PaidOrders,
            OrdersPerDay::make()->width(CardWidth::TwoThirds),
        ];
    }
}
