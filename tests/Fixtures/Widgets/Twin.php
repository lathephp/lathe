<?php

namespace Lathe\Tests\Fixtures\Widgets;

use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\Widget;

class Twin extends Widget
{
    public static function uriKey(): string
    {
        return 'order-count';
    }

    public function calculate(LatheRequest $request): mixed
    {
        return 0;
    }
}
