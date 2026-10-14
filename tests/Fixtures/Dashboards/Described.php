<?php

namespace Lathe\Tests\Fixtures\Dashboards;

use Lathe\Attributes\Dashboard\Description;
use Lathe\Attributes\Dashboard\Icon;
use Lathe\Attributes\Dashboard\ShowRefreshButton;
use Lathe\Dashboards\Dashboard;
use Lathe\Enums\Icon as IconName;
use Lathe\Http\Requests\LatheRequest;

#[Description('Numbers of the week')]
#[Icon(IconName::BarChart)]
#[ShowRefreshButton]
class Described extends Dashboard
{
    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}
