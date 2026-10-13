<?php

use Symfony\Component\Process\Process;

// The import map maps these specifiers to the files the manifest lists under "lathe-vendor:{specifier}".
const BUILD_OUTPUT_EXTERNALS = [
    'react',
    'react/jsx-runtime',
    'react/compiler-runtime',
    'react-dom',
    'react-dom/client',
    '@lathephp/core',
    '@lathephp/ui',
];

function buildOutputRepo(): string
{
    return dirname(__DIR__, 3);
}

function buildOutputRun(): void
{
    (new Process(['pnpm', 'build'], buildOutputRepo(), timeout: 600))->mustRun();
}

/** @return array<string, string> sha256 of every file under dist, keyed by its path */
function buildOutputTree(): array
{
    $dist = buildOutputRepo().'/dist';
    $tree = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $tree[substr($file->getPathname(), strlen($dist) + 1)] = hash_file('sha256', $file->getPathname());
    }

    ksort($tree);

    return $tree;
}

/** @return array{first: array<string, string>, second: array<string, string>} */
function buildOutputTwice(): array
{
    static $trees = null;

    if ($trees === null) {
        buildOutputRun();
        $first = buildOutputTree();
        buildOutputRun();
        $trees = ['first' => $first, 'second' => buildOutputTree()];
    }

    return $trees;
}

/** @return array<string, array{file: string, isEntry?: bool, css?: list<string>, imports?: list<string>, dynamicImports?: list<string>}> */
function buildOutputManifest(): array
{
    buildOutputTwice();

    return json_decode((string) file_get_contents(buildOutputRepo().'/dist/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
}

function buildOutputFile(string $path): string
{
    return (string) file_get_contents(buildOutputRepo().'/dist/'.$path);
}

it('builds byte-identical output from one source', function () {
    $trees = buildOutputTwice();

    expect($trees['first'])->not->toBeEmpty()
        ->and($trees['first'])->toHaveKey('manifest.json')
        ->and($trees['second'])->toBe($trees['first']);
});

it('puts no timestamp in any built file name', function () {
    $names = array_keys(buildOutputTwice()['first']);

    expect(preg_grep('/\d{10}|20\d{2}[-_]?\d{2}[-_]?\d{2}/', $names))->toBe([]);
});

it('writes every file the manifest lists', function () {
    $missing = [];

    foreach (buildOutputManifest() as $chunk) {
        foreach ([$chunk['file'], ...($chunk['css'] ?? [])] as $file) {
            if (! is_file(buildOutputRepo().'/dist/'.$file)) {
                $missing[] = $file;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('names the entry file with its content hash', function () {
    $entry = buildOutputManifest()['resources/js/app.tsx'];

    expect($entry['isEntry'] ?? false)->toBeTrue()
        ->and($entry['file'])->toMatch('#^assets/app-[a-z0-9]{8}\.js$#');
});

it('keeps a class used only inside the ui package in the stylesheet', function () {
    $css = buildOutputManifest()['resources/js/app.tsx']['css'] ?? [];

    expect($css)->toHaveCount(1)
        ->and(substr_count(buildOutputFile($css[0]), 'lathe-probe-class'))->toBe(1);
});

it('emits each import map specifier as its own entry file', function () {
    $manifest = buildOutputManifest();
    $files = [];

    foreach (BUILD_OUTPUT_EXTERNALS as $specifier) {
        $chunk = $manifest['lathe-vendor:'.$specifier] ?? null;

        expect($chunk)->not->toBeNull("manifest has no entry for {$specifier}")
            ->and($chunk['isEntry'] ?? false)->toBeTrue();

        $files[] = $chunk['file'];
    }

    expect(array_unique($files))->toHaveCount(count(BUILD_OUTPUT_EXTERNALS));
});

it('leaves react and the lathe packages as bare imports in the panel code', function () {
    $manifest = buildOutputManifest();
    $entry = $manifest['resources/js/app.tsx'];
    $panel = buildOutputFile($entry['file']);
    $pages = implode("\n", array_map(fn (string $key) => buildOutputFile($manifest[$key]['file']), $entry['dynamicImports'] ?? []));

    expect($entry['dynamicImports'] ?? [])->not->toBeEmpty()
        ->and($panel)->toContain('from"react-dom/client"')
        ->and($panel.$pages)->toContain('from"react/jsx-runtime"')
        ->and($pages)->toContain('from"react/compiler-runtime"')
        ->and($pages)->toContain('from"@lathephp/ui"')
        ->and($panel.$pages)->not->toContain('react.dev/errors');
});

it('ignores dist in git', function () {
    // The pattern /dist/ matches a folder, so dist must exist first.
    buildOutputTwice();
    $process = new Process(['git', 'check-ignore', 'dist'], buildOutputRepo());
    $process->run();

    expect(trim($process->getOutput()))->toBe('dist');
});

it('changes the entry hash when one source character changes', function () {
    $before = buildOutputManifest()['resources/js/app.tsx']['file'];
    $page = buildOutputRepo().'/resources/js/pages/Welcome.tsx';
    $source = (string) file_get_contents($page);

    expect($source)->toContain('<h1>Lathe</h1>');

    try {
        file_put_contents($page, str_replace('<h1>Lathe</h1>', '<h1>Lathf</h1>', $source));
        buildOutputRun();
        $after = json_decode(buildOutputFile('manifest.json'), true)['resources/js/app.tsx']['file'];
    } finally {
        file_put_contents($page, $source);
        buildOutputRun();
    }

    expect($after)->not->toBe($before);
});
