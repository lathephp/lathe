<?php

use Symfony\Component\Process\Process;

it('lists at least as many regression tests as the count file holds', function () {
    $root = dirname(__DIR__, 3);
    $process = new Process([PHP_BINARY, 'vendor/bin/pest', '--group=regression', '--list-tests'], $root, timeout: 120);
    $process->mustRun();

    preg_match_all('/^ - .*$/m', $process->getOutput(), $lines);
    $names = array_unique(array_map(fn (string $line) => preg_replace('/"\(.*\)"$/', '', $line), $lines[0]));

    expect(count($names))->toBeGreaterThanOrEqual((int) trim((string) file_get_contents("{$root}/tests/regression-count.txt")));
});
