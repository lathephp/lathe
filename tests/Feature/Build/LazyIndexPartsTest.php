<?php

use Symfony\Component\Process\Process;

// It reads the built manifest, so it needs Node and a build.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

it('keeps the index\'s on-demand parts and the form-only inputs out of its first load', function () {
    $root = dirname(__DIR__, 3);

    if (! is_file("{$root}/dist/manifest.json")) {
        (new Process(['pnpm', 'build'], $root, timeout: 600))->mustRun();
    }

    $process = new Process(['node', 'scripts/size.mjs', 'dist', '--json'], $root, timeout: 120);
    $process->mustRun();
    $routes = array_column(json_decode($process->getOutput(), true), 'files', 'route');
    $manifest = json_decode((string) file_get_contents("{$root}/dist/manifest.json"), true);

    foreach (['resources/js/index/FilterPanel.tsx', 'resources/js/index/RowMenuPanel.tsx', 'resources/js/index/ColumnPicker.tsx', 'resources/js/index/TableSummaryRow.tsx', 'resources/js/index/DeleteTabDialog.tsx', 'resources/js/index/Pagination.tsx', 'resources/js/index/InlineEditor.tsx', 'resources/js/index/PreviewPanel.tsx', 'resources/js/fields/number/TokenInput.tsx', 'resources/js/fields/slug/SlugInput.tsx'] as $key) {
        expect($manifest)->toHaveKey($key)
            ->and($routes['/lathe/resources/orders'])->not->toContain($manifest[$key]['file']);
    }
});
