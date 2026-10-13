<?php

namespace Lathe\Dashboards;

use Lathe\Http\Requests\LatheRequest;

/**
 * The dashboard at the panel home.
 */
class Main extends Dashboard
{
    public function name(): string
    {
        return 'Dashboard';
    }

    public function widgets(LatheRequest $request): array
    {
        return [];
    }
}
