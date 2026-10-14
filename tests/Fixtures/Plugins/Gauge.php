<?php

namespace Lathe\Tests\Fixtures\Plugins;

use Illuminate\Http\Request;
use Lathe\Enums\Icon;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;

class Gauge extends Plugin
{
    public function menu(Request $request): ?array
    {
        return [MenuSection::section('Gauge', [MenuItem::plugin(static::class)->icon(Icon::Activity)])];
    }
}
