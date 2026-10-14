<?php

namespace Lathe\Tests\Fixtures\Lenses;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\ValueOrders;
use Workbench\App\Lathe\Lenses\PendingOrders;

class ScopedPendingOrders extends PendingOrders
{
    public function widgets(LatheRequest $request): array
    {
        return [new ValueOrders];
    }
}
