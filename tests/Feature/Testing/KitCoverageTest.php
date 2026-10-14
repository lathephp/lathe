<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Lathe\Testing\DashboardTester;
use Lathe\Testing\LatheTest;
use Lathe\Testing\ResourceTester;

/**
 * Each action and batch route of a Resource and of a lens, by verb and the path after
 * resources/{resource} or resources/{resource}/lenses/{lens}, with the kit method that sends it.
 */
const KIT_ACTION_ROUTES = [
    'GET actions' => 'actions',
    'GET actions/{action}/form' => 'actionForm',
    'POST actions/{action}/uploads/{field}' => 'startActionUpload',
    'POST action' => 'runAction',
    'PATCH action' => 'actionDependencies',
    'GET action/associatable' => 'actionAssociatable',
    'DELETE batch' => 'batchDelete',
    'DELETE batch/force' => 'batchForceDelete',
    'PUT batch/restore' => 'batchRestore',
];

it('has a kit method for every action and batch route', function () {
    $unmapped = [];

    foreach (Router::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (preg_match('#^lathe/api/resources/\{resource\}(?:/lenses/\{lens\})?/((?:actions?|batch)(?:/.*)?)$#', $route->uri(), $match) !== 1) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $key = "{$method} {$match[1]}";
            $kit = KIT_ACTION_ROUTES[$key] ?? null;

            if ($kit === null || ! method_exists(ResourceTester::class, $kit)) {
                $unmapped[] = $key;
            }
        }
    }

    expect($unmapped)->toBe([]);
});

it('finds the batch routes it maps', function () {
    $uris = collect(Router::getRoutes()->getRoutes())->map(fn (Route $route) => $route->uri())->all();

    expect($uris)->toContain('lathe/api/resources/{resource}/batch', 'lathe/api/resources/{resource}/batch/force', 'lathe/api/resources/{resource}/batch/restore');
});

/**
 * Each dashboard and widget route, by verb and the path after lathe/api, with the kit class
 * and method that sends it.
 */
const KIT_WIDGET_ROUTES = [
    'GET dashboards' => [LatheTest::class, 'dashboards'],
    'GET dashboards/{dashboard}' => [DashboardTester::class, 'show'],
    'GET dashboards/{dashboard}/widgets' => [DashboardTester::class, 'widgets'],
    'GET dashboards/{dashboard}/widgets/{widget}' => [DashboardTester::class, 'widget'],
    'GET resources/{resource}/widgets' => [ResourceTester::class, 'widgets'],
    'GET resources/{resource}/widgets/{widget}' => [ResourceTester::class, 'widget'],
    'GET resources/{resource}/lenses/{lens}/widgets' => [ResourceTester::class, 'widgets'],
    'GET resources/{resource}/lenses/{lens}/widgets/{widget}' => [ResourceTester::class, 'widget'],
];

it('has a kit method for every dashboard and widget route', function () {
    $found = [];

    foreach (Router::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (preg_match('#^lathe/api/((?:dashboards|resources/\{resource\}(?:/lenses/\{lens\})?/widgets)(?:/.*)?)$#', $route->uri(), $match) !== 1) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $kit = KIT_WIDGET_ROUTES["{$method} {$match[1]}"] ?? null;
            $found["{$method} {$match[1]}"] = $kit !== null && method_exists($kit[0], $kit[1]);
        }
    }

    ksort($found);
    $expected = array_fill_keys(array_keys(KIT_WIDGET_ROUTES), true);
    ksort($expected);

    expect($found)->toBe($expected);
});

it('kit_calls_match_their_signatures', function () {
    $signature = fn (string $class, string $method) => array_map(
        fn (ReflectionParameter $parameter) => $parameter->getType().' $'.$parameter->getName().($parameter->isDefaultValueAvailable() ? ' = '.json_encode($parameter->getDefaultValue()) : ''),
        (new ReflectionMethod($class, $method))->getParameters(),
    );
    $returns = fn (string $class, string $method) => (string) (new ReflectionMethod($class, $method))->getReturnType();

    expect($signature(LatheTest::class, 'dashboards'))->toBe([])
        ->and($signature(LatheTest::class, 'dashboard'))->toBe(['string $uriKey'])
        ->and($returns(LatheTest::class, 'dashboard'))->toBe(DashboardTester::class)
        ->and($signature(DashboardTester::class, 'show'))->toBe([])
        ->and($signature(DashboardTester::class, 'widgets'))->toBe([])
        ->and($signature(DashboardTester::class, 'widget'))->toBe(['string $widget', 'array $params = []'])
        ->and($signature(ResourceTester::class, 'widgets'))->toBe(['array $params = []'])
        ->and($signature(ResourceTester::class, 'widget'))->toBe(['string $widget', 'array $params = []'])
        ->and($signature(ResourceTester::class, 'lens'))->toBe(['string $lens'])
        ->and($returns(ResourceTester::class, 'lens'))->toBe('static');
})->group('regression');
