<?php

use Symfony\Component\Process\Process;

// It reads the built manifest, so it needs Node and a build.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

/** @return array<string, array{file: string, imports?: list<string>}> */
function lazyChartsManifest(): array
{
    $root = dirname(__DIR__, 3);

    if (! is_file("{$root}/dist/manifest.json")) {
        (new Process(['pnpm', 'build'], $root, timeout: 600))->mustRun();
    }

    return json_decode((string) file_get_contents("{$root}/dist/manifest.json"), true, flags: JSON_THROW_ON_ERROR);
}

function lazyChartsSource(string $file): string
{
    return (string) file_get_contents(dirname(__DIR__, 3).'/dist/'.$file);
}

const LAZY_CHARTS_SUBPATH_IMPORT = '/(?:from|import)\s*["\']@lathephp\/ui\/[^"\']+["\']/';

/** @return array<string, string> file of each chunk the app entry and the three measured routes import statically, by manifest key */
function lazyChartsFirstPaint(): array
{
    $manifest = lazyChartsManifest();
    $keys = ['resources/js/app.tsx', 'resources/js/pages/Dashboard.tsx', 'resources/js/pages/Resources/Index.tsx', 'resources/js/pages/Resources/Form.tsx'];
    $seen = [];

    while ($keys !== []) {
        $key = array_shift($keys);

        if (isset($seen[$key]) || ! isset($manifest[$key])) {
            continue;
        }

        $seen[$key] = $manifest[$key]['file'];
        array_push($keys, ...($manifest[$key]['imports'] ?? []));
    }

    return $seen;
}

it('still keeps every @lathephp/ui subpath out of the static imports of first-paint chunks', function () {
    $seen = lazyChartsFirstPaint();
    $offenders = array_keys(array_filter($seen, fn (string $file) => preg_match(LAZY_CHARTS_SUBPATH_IMPORT, lazyChartsSource($file)) === 1));

    expect($seen)->toHaveKey('resources/js/pages/Resources/Index.tsx')
        ->and($offenders)->toBe([]);
});

it('keeps the @lathephp/ui main entry out of the static imports of first-paint chunks', function () {
    $seen = lazyChartsFirstPaint();
    $offenders = array_keys(array_filter($seen, fn (string $file) => preg_match('/(?:from|import)\s*["\']@lathephp\/ui["\']/', lazyChartsSource($file)) === 1));

    expect($seen)->toHaveKey('resources/js/pages/Dashboard.tsx')
        ->and($offenders)->toBe([]);
});

it('builds the chart cards as one chunk that imports @lathephp/ui/chart', function () {
    $importers = array_values(array_filter(
        array_unique(array_column(lazyChartsManifest(), 'file')),
        fn (string $file) => str_ends_with($file, '.js') && preg_match('/(?:from|import)\s*["\']@lathephp\/ui\/chart["\']/', lazyChartsSource($file)) === 1,
    ));

    expect($importers)->toHaveCount(1)
        ->and($importers[0])->toMatch('#^assets/charts-[a-z0-9]{8}\.js$#');
});
