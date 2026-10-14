<?php

namespace Lathe\Menu;

use Closure;
use Illuminate\Http\Request;

/**
 * The menu callbacks an app registered. The container keeps one instance, so a long-lived
 * worker holds them without a static property.
 */
class MenuRegistry
{
    /** @var (Closure(Request, Menu): mixed)|null */
    public ?Closure $main = null;

    /** @var (Closure(Request, Menu): mixed)|null */
    public ?Closure $user = null;
}
