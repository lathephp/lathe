<?php

namespace Lathe\Tests\Fixtures\Lenses;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\Secret;
use Workbench\App\Lathe\Lenses\PendingOrders;

class CountedOrders extends PendingOrders
{
    public function widgets(LatheRequest $request): array
    {
        return [new OrderCount, new Secret];
    }
}
