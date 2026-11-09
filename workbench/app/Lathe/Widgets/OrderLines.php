<?php

namespace Workbench\App\Lathe\Widgets;

use Lathe\Attributes\Widget\OnlyOnDetail;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\OrderItem;

/**
 * The lines of the order on the page.
 */
#[OnlyOnDetail]
class OrderLines extends ValueWidget
{
    public function name(): string
    {
        return 'Order lines';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return new ValueResult(OrderItem::where('order_id', $request->query('resourceId'))->count());
    }
}
