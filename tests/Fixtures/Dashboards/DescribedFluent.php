<?php

namespace Lathe\Tests\Fixtures\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;

class DescribedFluent extends Dashboard
{
    public function __construct()
    {
        $this->description('Numbers of the week')->icon(Icon::BarChart)->showRefreshButton();
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}
