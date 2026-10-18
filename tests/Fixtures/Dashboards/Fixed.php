<?php

namespace Lathe\Tests\Fixtures\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\OrderCount;

class Fixed extends Dashboard
{
    public function __construct()
    {
        $this->customizable(false);
    }

    public function widgets(LatheRequest $request): array
    {
        return [new OrderCount];
    }
}
