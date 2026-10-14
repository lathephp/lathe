<?php

namespace Lathe\Tests\Fixtures\Lenses;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\FilteredOrders;
use Workbench\App\Lathe\Lenses\PendingOrders;

class FilteredPendingOrders extends PendingOrders
{
    public function widgets(LatheRequest $request): array
    {
        return [new FilteredOrders];
    }
}
