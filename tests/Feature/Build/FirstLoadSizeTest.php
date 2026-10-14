<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

// pnpm size reads the built manifest, so these tests need Node.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

function sizeRun(string $dist): Process
{
    $process = new Process(['node', 'scripts/size.mjs', $dist, '--json'], dirname(__DIR__, 3), timeout: 120);
    $process->run();

    return $process;
}

it('keeps each route of the built panel inside 200 KB of gzipped JavaScript', function () {
    $root = dirname(__DIR__, 3);

    if (! is_file("{$root}/dist/manifest.json")) {
        (new Process(['pnpm', 'build'], $root, timeout: 600))->mustRun();
    }

    $process = sizeRun("{$root}/dist");
    $results = json_decode($process->getOutput(), true);

    expect($process->getExitCode())->toBe(0, $process->getOutput())
        ->and(array_column($results, 'route'))->toBe(['/lathe', '/lathe/resources/orders', '/lathe/resources/orders/new']);

    foreach ($results as $result) {
        expect($result['bytes'])->toBeLessThanOrEqual(200_000);
    }
});

it('counts the entry, the page, the import map entries and their imports, but no CSS', function () {
    $dist = sys_get_temp_dir().'/size-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$dist}/assets");
    $manifest = [
        'resources/js/app.tsx' => ['file' => 'assets/app.js', 'imports' => ['_shared.js'], 'css' => ['assets/app.css']],
        '_shared.js' => ['file' => 'assets/shared.js'],
        'lathe-vendor:react' => ['file' => 'assets/react.js'],
        'resources/js/pages/Dashboard.tsx' => ['file' => 'assets/Dashboard.js'],
        'resources/js/pages/Resources/Index.tsx' => ['file' => 'assets/Index.js', 'imports' => ['_big.js']],
        '_big.js' => ['file' => 'assets/big.js'],
        'resources/js/pages/Other.tsx' => ['file' => 'assets/Other.js'],
    ];
    File::put("{$dist}/manifest.json", json_encode($manifest));

    foreach (['app', 'shared', 'react', 'Dashboard', 'Index', 'Other'] as $name) {
        File::put("{$dist}/assets/{$name}.js", "export const {$name} = 1;\n");
    }
    File::put("{$dist}/assets/app.css", 'body{}');
    // Random bytes do not compress, so this chunk alone is over the budget.
    File::put("{$dist}/assets/big.js", random_bytes(210_000));

    $process = sizeRun($dist);
    $results = array_column(json_decode($process->getOutput(), true), null, 'route');

    expect($process->getExitCode())->toBe(1)
        ->and($results['/lathe']['files'])->toBe(['assets/Dashboard.js', 'assets/app.js', 'assets/react.js', 'assets/shared.js'])
        ->and($results['/lathe']['bytes'])->toBeLessThan(200_000)
        ->and($results['/lathe/resources/orders']['bytes'])->toBeGreaterThan(200_000);

    File::deleteDirectory($dist);
});

it('counts a ui subpath entry only on a route whose first load imports it', function () {
    $dist = sys_get_temp_dir().'/size-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$dist}/assets");
    $manifest = [
        'resources/js/app.tsx' => ['file' => 'assets/app.js'],
        'lathe-vendor:@lathephp/ui' => ['file' => 'assets/ui.js'],
        'lathe-vendor:@lathephp/ui/chart' => ['file' => 'assets/chart.js', 'imports' => ['_scale.js']],
        '_scale.js' => ['file' => 'assets/scale.js'],
        'resources/js/pages/Dashboard.tsx' => ['file' => 'assets/Dashboard.js'],
        'resources/js/pages/Resources/Index.tsx' => ['file' => 'assets/Index.js'],
        'resources/js/pages/Resources/Form.tsx' => ['file' => 'assets/Form.js'],
    ];
    File::put("{$dist}/manifest.json", json_encode($manifest));

    foreach (['app', 'ui', 'chart', 'scale', 'Form'] as $name) {
        File::put("{$dist}/assets/{$name}.js", "export const {$name} = 1;\n");
    }
    // The dashboard imports the chart entry statically, the index only when a card asks.
    File::put("{$dist}/assets/Dashboard.js", 'import{BarChart as e}from"@lathephp/ui/chart";export const d=e;');
    File::put("{$dist}/assets/Index.js", 'export const i=()=>import("@lathephp/ui/chart");');

    $results = array_column(json_decode(sizeRun($dist)->getOutput(), true), 'files', 'route');

    expect($results['/lathe'])->toBe(['assets/Dashboard.js', 'assets/app.js', 'assets/chart.js', 'assets/scale.js', 'assets/ui.js'])
        ->and($results['/lathe/resources/orders'])->toBe(['assets/Index.js', 'assets/app.js', 'assets/ui.js'])
        ->and($results['/lathe/resources/orders/new'])->toBe(['assets/Form.js', 'assets/app.js', 'assets/ui.js']);

    File::deleteDirectory($dist);
});
