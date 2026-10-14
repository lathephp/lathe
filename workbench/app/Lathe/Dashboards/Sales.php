<?php

namespace Workbench\App\Lathe\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;

/**
 * The workbench home page.
 */
class Sales extends Dashboard
{
    public static function uriKey(): string
    {
        return 'main';
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}
