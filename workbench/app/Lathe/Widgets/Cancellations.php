<?php

namespace Workbench\App\Lathe\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;
use Workbench\App\Models\Order;

/**
 * The orders cancelled in the range; fewer is better, so a fall shows in green.
 */
class Cancellations extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        /** @var Builder<Model> $cancelled */
        $cancelled = Order::where('status', 'cancelled');

        return $this->count($request, $cancelled, null, 'placed_at')->lowerIsBetter();
    }
}
