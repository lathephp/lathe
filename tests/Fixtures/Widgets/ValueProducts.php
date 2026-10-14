<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Results\ValueResult;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Product;

/**
 * Counts the products of the requested range.
 */
class ValueProducts extends ValueWidget
{
    public function calculate(LatheRequest $request): ValueResult
    {
        return $this->count($request, Product::class);
    }
}
