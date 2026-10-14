<?php

namespace Lathe\Tests\Fixtures\Dashboards;

use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;
use RuntimeException;

/**
 * A dashboard whose widgets() throws and counts its calls.
 */
class Exploding extends Dashboard
{
    public static int $calls = 0;

    public function widgets(LatheRequest $request): array
    {
        static::$calls++;

        throw new RuntimeException('widgets() ran');
    }
}
