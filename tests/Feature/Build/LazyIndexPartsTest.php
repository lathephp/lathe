<?php

use Symfony\Component\Process\Process;

// It reads the built manifest, so it needs Node and a build.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

it('keeps the filter panel and the row menu\'s list out of the index\'s first load', function () {
    $root = dirname(__DIR__, 3);

    if (! is_file("{$root}/dist/manifest.json")) {
        (new Process(['pnpm', 'build'], $root, timeout: 600))->mustRun();
    }

    $process = new Process(['node', 'scripts/size.mjs', 'dist', '--json'], $root, timeout: 120);
    $process->mustRun();
    $routes = array_column(json_decode($process->getOutput(), true), 'files', 'route');
    $manifest = json_decode((string) file_get_contents("{$root}/dist/manifest.json"), true);

    foreach (['resources/js/index/FilterPanel.tsx', 'resources/js/index/RowMenuPanel.tsx'] as $key) {
        expect($manifest)->toHaveKey($key)
            ->and($routes['/lathe/resources/orders'])->not->toContain($manifest[$key]['file']);
    }
});
