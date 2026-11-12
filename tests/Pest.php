<?php

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Lathe\Http\PanelRoutes;
use Lathe\Tests\TestCase;
use Pest\Expectation;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Run the test as an app on the zone. Laravel sets PHP's default zone from app.timezone at boot,
 * and Eloquent reads a datetime column in it, so the test moves both and puts them back after.
 */
function appTimezone(string $zone): void
{
    $before = date_default_timezone_get();
    config(['app.timezone' => $zone]);
    date_default_timezone_set($zone);
    test()->beforeApplicationDestroyed(fn () => date_default_timezone_set($before));
}

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
 * SQL text written with "double quoted" identifiers, quoted the way the test database
 * quotes them: MySQL and MariaDB use backticks.
 */
function quotedSql(string $sql): string
{
    return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
        ? str_replace('"', '`', $sql)
        : $sql;
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

/**
 * Hold the build lock until the test ends. Test files run in parallel processes, and the ones
 * that run pnpm build or read dist would otherwise write the same dist folders under each other.
 */
function holdBuildLock(): void
{
    $handle = fopen(sys_get_temp_dir().'/lathe-tests-build.lock', 'c');

    if ($handle === false) {
        throw new RuntimeException('Cannot open the build lock.');
    }

    flock($handle, LOCK_EX);
    test()->beforeApplicationDestroyed(function () use ($handle) {
        flock($handle, LOCK_UN);
        fclose($handle);
    });
}

/**
 * A JSON column's value with object keys sorted at every level: MySQL stores keys in its own
 * order, so a stored row compares by content, list order and types, not key order.
 */
function keysSorted(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(keysSorted(...), $value);

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

expect()->extend('toMatchStoredJson', function (mixed $expected) {
    /** @var Expectation<mixed> $this */
    expect(keysSorted($this->value))->toBe(keysSorted($expected));

    return $this;
});

/**
 * Opens the panel home once, so a lazy sidebar badge (the workbench Insights plugin counts
 * open orders) is cached for the user and a query budget counts the page's own queries.
 */
function warmNavigationBadges(): void
{
    test()->get('/lathe')->assertOk();
}
