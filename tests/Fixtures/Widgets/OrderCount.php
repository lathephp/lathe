<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;
use Workbench\App\Models\Order;

/**
 * Counts the orders and records what it was asked.
 */
class OrderCount extends Widget
{
    /** @var list<array<string, mixed>> */
    public static array $requests = [];

    public function calculate(LatheRequest $request): mixed
    {
        static::$requests[] = $request->query();

        return Order::count();
    }
}
