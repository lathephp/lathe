<?php

use Symfony\Component\Process\Process;

// These tests run pnpm build, so they need Node.
pest()->group('frontend');

beforeEach(fn () => holdBuildLock());

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
        // The first build's files, so a mismatch can show what changed.
        (new Process(['cp', '-R', buildOutputRepo().'/dist', buildOutputFirstCopy()]))->mustRun();
        buildOutputRun();
        $trees = ['first' => $first, 'second' => buildOutputTree()];
    }

    return $trees;
}

function buildOutputFirstCopy(): string
{
    static $path = null;

    if ($path === null) {
        $path = sys_get_temp_dir().'/lathe-first-build-'.bin2hex(random_bytes(4));
        register_shutdown_function(fn () => (new Process(['rm', '-rf', $path]))->run());
    }

    return $path;
}

/**
 * The parts that differ between the two builds' copies of each changed file, paired by the
 * name before the content hash; minified CSS and JS split at ; and } to make lines.
 *
 * @param  array<string, string>  $first
 * @param  array<string, string>  $second
 */
function buildOutputDifference(array $first, array $second): string
{
    $stem = fn (string $path) => (string) preg_replace('/-[0-9a-z]{8}(\.\w+)$/', '$1', $path);
    $seconds = [];

    foreach (array_keys($second) as $path) {
        $seconds[$stem($path)] = $path;
    }

    $report = [];

    foreach ($first as $path => $hash) {
        $other = $seconds[$stem($path)] ?? null;

        if ($other === null || ($other === $path && $second[$other] === $hash) || $path === 'manifest.json') {
            continue;
        }

        $split = fn (string $text) => preg_split('/(?<=[;}])/', $text) ?: [];
        $before = $split((string) file_get_contents(buildOutputFirstCopy().'/'.$path));
        $after = $split(buildOutputFile($other));
        $report[] = "{$path} -> {$other}:\n- ".implode("\n- ", array_slice(array_diff($before, $after), 0, 10))."\n+ ".implode("\n+ ", array_slice(array_diff($after, $before), 0, 10));
    }

    return implode("\n", $report);
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
        ->and($trees['second'])->toBe($trees['first'], buildOutputDifference($trees['first'], $trees['second']));
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

it('leaves no CommonJS require of an import map specifier in any chunk', function () {
    buildOutputRun();
    $calls = [];

    foreach (array_keys(buildOutputTree()) as $path) {
        // A require() of an external throws "Calling `require` for react" in the browser.
        if (str_ends_with($path, '.js') && preg_match('/\(\s*[`"\'](react|react-dom|react\/jsx-runtime|react-dom\/client)[`"\']\s*\)/', buildOutputFile($path), $match) === 1) {
            $calls[] = "{$path}: {$match[0]}";
        }
    }

    expect($calls)->toBe([]);
});

it('ignores dist in git', function () {
    // The pattern /dist/ matches a folder, so dist must exist first. A release commit tracks dist,
    // so --no-index asks the ignore rules alone.
    buildOutputTwice();
    $process = new Process(['git', 'check-ignore', '--no-index', 'dist'], buildOutputRepo());
    $process->run();

    expect(trim($process->getOutput()))->toBe('dist');
});

it('changes the entry hash when one source character changes', function () {
    $before = buildOutputManifest()['resources/js/app.tsx']['file'];
    $page = buildOutputRepo().'/resources/js/pages/Welcome.tsx';
    $source = (string) file_get_contents($page);

    expect($source)->toContain('<main>');

    try {
        file_put_contents($page, str_replace('<main>', '<main id="hash-probe">', $source));
        buildOutputRun();
        $after = json_decode(buildOutputFile('manifest.json'), true)['resources/js/app.tsx']['file'];
    } finally {
        file_put_contents($page, $source);
        buildOutputRun();
    }

    expect($after)->not->toBe($before);
});
