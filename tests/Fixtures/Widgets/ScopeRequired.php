<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\AggregateQuery;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * Counts orders only where a host Resource scopes them.
 */
class ScopeRequired extends ValueWidget
{
    public function calculate(LatheRequest $request): ValueResult
    {
        return $this->count($request, $this->requireResourceScope(AggregateQuery::builderFor(Order::class)));
    }
}
