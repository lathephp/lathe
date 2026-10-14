<?php

namespace Lathe\Tests\Fixtures\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;

class Revenue extends Dashboard
{
    public static function uriKey(): string
    {
        return 'sales';
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}
