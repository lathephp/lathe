<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\BarWidget;
use Lathe\Widgets\Results\BarResult;

/**
 * Bars built by hand, in dollars, with labels a spreadsheet must not read as formulas.
 */
class BigBars extends BarWidget
{
    public function calculate(LatheRequest $request): BarResult
    {
        return $this->result(['Paid, online' => 1948466.99, '=HYPERLINK("x")' => 1581, 'Refunded' => -12])->dollars();
    }
}
