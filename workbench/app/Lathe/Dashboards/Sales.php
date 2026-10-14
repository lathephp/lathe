<?php

namespace Workbench\App\Lathe\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Lathe\Widgets\Orders;
use Workbench\App\Lathe\Widgets\PaidOrders;
use Workbench\App\Lathe\Widgets\Revenue;

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
        return [new Orders, new Revenue, new PaidOrders];
    }
}
