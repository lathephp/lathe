<?php

namespace Workbench\App\Lathe\Dashboards;

use Lathe\Attributes\Dashboard\ShowRefreshButton;
use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\CustomWidget;
use Lathe\Widgets\HelpWidget;
use Workbench\App\Lathe\Widgets\CustomersByCountry;
use Workbench\App\Lathe\Widgets\OrderCompletion;
use Workbench\App\Lathe\Widgets\Orders;
use Workbench\App\Lathe\Widgets\OrdersByStatus;
use Workbench\App\Lathe\Widgets\OrdersPerDay;
use Workbench\App\Lathe\Widgets\PaidOrders;
use Workbench\App\Lathe\Widgets\ProductsByCategory;
use Workbench\App\Lathe\Widgets\SalesByWeekday;
use Workbench\App\Lathe\Widgets\TopCustomers;

/**
 * One card of each widget type, over the demo store.
 */
#[ShowRefreshButton]
class WidgetGallery extends Dashboard
{
    public function widgets(LatheRequest $request): array
    {
        return [
            new Orders,
            new OrdersPerDay,
            new PaidOrders,
            new TopCustomers,
            HelpWidget::make()->content('Each card on this page is one of the 11 widget types, drawn over the demo orders, customers and products.'),
            CustomWidget::make('Clock')->withComponent('workbench-clock'),
            new OrdersByStatus,
            new ProductsByCategory,
            new CustomersByCountry,
            new SalesByWeekday,
            new OrderCompletion,
        ];
    }
}
