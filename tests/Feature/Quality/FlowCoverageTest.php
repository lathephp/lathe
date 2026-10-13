<?php

function browserFlows(): array
{
    return require dirname(__DIR__, 2).'/Browser/flows.php';
}

it('lists the flows of the panel, the index and the records', function () {
    expect(array_keys(browserFlows()))->toBe([
        'login', 'orders-index', 'heartbeat', 'sidebar', 'forbidden', 'not-found', 'stale-assets', 'plugin-load-failure', 'index-search-sort', 'search-palette', 'index-filters', 'lens', 'index-rows', 'index-tabs', 'narrow-screen',
        'record-show-path', 'record-forms', 'record-layout', 'inline-edit', 'record-actions', 'uploads', 'stale-edit', 'record-a11y-csp',
    ]);
});

it('has a browser test for every listed flow', function () {
    $missing = [];

    foreach (browserFlows() as $flow => $file) {
        $path = dirname(__DIR__, 2).'/Browser/'.$file;
        $source = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^(it|test)\(/m', $source) !== 1) {
            $missing[] = $flow;
        }
    }

    expect($missing)->toBe([]);
});
