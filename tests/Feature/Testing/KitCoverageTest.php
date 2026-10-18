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
    'GET dashboards/{dashboard}/widgets/{widget}/csv' => [DashboardTester::class, 'csv'],
    'PUT dashboards/{dashboard}/layout' => [DashboardTester::class, 'saveLayout'],
    'DELETE dashboards/{dashboard}/layout' => [DashboardTester::class, 'resetLayout'],
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

/**
 * Each record, lens, saved tab and search route, by verb and the path after lathe/, with
 * the kit class and method that sends it. The two page routes answer show() and lenses().
 */
const KIT_RECORD_ROUTES = [
    'GET resources/{resource}' => [ResourceTester::class, 'lenses'],
    'GET resources/{resource}/{resourceId}' => [ResourceTester::class, 'show'],
    'GET api/resources/{resource}' => [ResourceTester::class, 'index'],
    'POST api/resources/{resource}' => [ResourceTester::class, 'store'],
    'GET api/resources/{resource}/count' => [ResourceTester::class, 'count'],
    'GET api/resources/{resource}/filters' => [ResourceTester::class, 'filters'],
    'GET api/resources/{resource}/lenses/{lens}' => [ResourceTester::class, 'index'],
    'GET api/resources/{resource}/lenses/{lens}/count' => [ResourceTester::class, 'count'],
    'GET api/resources/{resource}/lenses/{lens}/filters' => [ResourceTester::class, 'filters'],
    'GET api/resources/{resource}/saved-tabs' => [ResourceTester::class, 'savedTabs'],
    'POST api/resources/{resource}/saved-tabs' => [ResourceTester::class, 'createSavedTab'],
    'PUT api/resources/{resource}/saved-tabs/reorder' => [ResourceTester::class, 'reorderSavedTabs'],
    'PUT api/resources/{resource}/saved-tabs/{savedTab}' => [ResourceTester::class, 'updateSavedTab'],
    'DELETE api/resources/{resource}/saved-tabs/{savedTab}' => [ResourceTester::class, 'deleteSavedTab'],
    'GET api/search' => [LatheTest::class, 'search'],
];

it('has a kit method for every record, lens, saved tab and search route', function () {
    $found = [];

    foreach (Router::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (preg_match('#^lathe/(resources/\{resource\}(?:/\{resourceId\})?|api/search|api/resources/\{resource\}(?:/lenses/\{lens\})?(?:/count|/filters)?|api/resources/\{resource\}/saved-tabs(?:/.*)?)$#', $route->uri(), $match) !== 1) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $kit = KIT_RECORD_ROUTES["{$method} {$match[1]}"] ?? null;
            $found["{$method} {$match[1]}"] = $kit !== null && method_exists($kit[0], $kit[1]);
        }
    }

    ksort($found);
    $expected = array_fill_keys(array_keys(KIT_RECORD_ROUTES), true);
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
