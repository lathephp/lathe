<?php

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Routing\RouteCollection;
use Lathe\Http\PanelRoutes;
use Lathe\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Register the panel routes again after a test changes lathe.path or lathe.domain.
 */
function reloadLatheRoutes(): void
{
    $router = app('router');
    $router->setRoutes(new RouteCollection);
    app(PanelRoutes::class)->register();
    $router->getRoutes()->refreshNameLookups();
}

/**
 * Remove a gate ability, for tests of what happens when the app never defined it.
 */
function forgetGate(string $ability): void
{
    $gate = app(Gate::class);
    $abilities = (fn () => $this->abilities)->call($gate);
    unset($abilities[$ability]);
    (fn () => $this->abilities = $abilities)->call($gate);
}
