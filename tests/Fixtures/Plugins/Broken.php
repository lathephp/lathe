<?php

namespace Lathe\Tests\Fixtures\Plugins;

use Illuminate\Http\Request;
use Lathe\Plugins\Plugin;
use RuntimeException;

/**
 * A plugin whose sidebar menu throws, so the panel shell cannot be built.
 */
class Broken extends Plugin
{
    public function menu(Request $request): ?array
    {
        throw new RuntimeException('The menu broke.');
    }
}
