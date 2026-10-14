<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Lathe\Testing\ResourceTester;

/**
 * Each action and batch route of a Resource and of a lens, by verb and the path after
 * resources/{resource} or resources/{resource}/lenses/{lens}, with the kit method that sends it.
 */
const KIT_ACTION_ROUTES = [
    'GET actions' => 'actions',
    'GET actions/{action}/form' => 'actionForm',
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
