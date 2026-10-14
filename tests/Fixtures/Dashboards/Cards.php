<?php

namespace Lathe\Tests\Fixtures\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\OrderCount;
use Lathe\Tests\Fixtures\Widgets\Secret;
use Lathe\Widgets\Widget;

class Cards extends Dashboard
{
    /** @var list<Widget>|null */
    public static ?array $widgets = null;

    public function widgets(LatheRequest $request): array
    {
        return static::$widgets ?? [new OrderCount, new Secret];
    }
}
