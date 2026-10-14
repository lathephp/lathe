<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Closure;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ProgressWidget;

/**
 * A progress widget whose value is whatever the test sets, run with the widget as $this.
 */
class ProgressProbe extends ProgressWidget
{
    /** @var (Closure(LatheRequest): mixed)|null */
    public static ?Closure $using = null;

    public function calculate(LatheRequest $request): mixed
    {
        return (static::$using ?? fn () => null)->call($this, $request);
    }
}
