<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * The snake_case names of the Pest tests in the regression group, browser tests included.
 *
 * @return list<string>
 */
function regressionTestNames(string $root): array
{
    $names = [];

    foreach ([[], ['tests/Browser']] as $paths) {
        $process = new Process([PHP_BINARY, 'vendor/bin/pest', ...$paths, '--group=regression', '--list-tests'], $root, timeout: 120);
        $process->mustRun();

        preg_match_all('/::__pest_evaluable_(?:it_|test_)?([a-z0-9_]+)/', $process->getOutput(), $matches);

        foreach ($matches[1] as $name) {
            $names[] = str_replace('__', '_', (string) preg_replace('/_with_data_set.*$|"\(.*$/', '', $name));
        }
    }

    return array_values(array_unique($names));
}

it('pins every name of the regression ledger with a test', function () {
    $root = dirname(__DIR__, 3);
    $ledger = array_values(array_filter(array_map('trim', file("{$root}/tests/regression-ledger.txt") ?: []), fn (string $line) => $line !== '' && ! str_starts_with($line, '#')));
    $pest = regressionTestNames($root);

    $vitest = collect(File::allFiles("{$root}/resources/js"))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getFilename(), '.test.'))
        ->map(fn (SplFileInfo $file) => File::get($file->getPathname()))
        ->implode("\n");

    $missing = array_values(array_filter($ledger, fn (string $name) => ! in_array($name, $pest, true) && ! str_contains($vitest, "it('{$name}'")));

    expect($ledger)->toHaveCount(71)
        ->and($missing)->toBe([], 'no regression test pins: '.implode(', ', $missing));
});
