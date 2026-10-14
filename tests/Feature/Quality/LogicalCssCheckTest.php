<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function runLogicalCssCheck(string $repo): ProcessResult
{
    return Process::path($repo)->run(['bash', dirname(__DIR__, 3).'/scripts/check-logical-css.sh']);
}

function plantedRepo(string $path, string $content): string
{
    $repo = sys_get_temp_dir().'/logical-css-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists(dirname("{$repo}/{$path}"));
    File::put("{$repo}/{$path}", $content);
    Process::path($repo)->run('git init -q -b main && git add -A')->throw();

    return $repo;
}

it('exits 0 on the tree', function () {
    $result = runLogicalCssCheck(dirname(__DIR__, 3));

    expect($result->exitCode())->toBe(0, $result->output());
});

it('fails on a physical utility', function (string $class) {
    $repo = plantedRepo('resources/js/Box.tsx', "export const Box = () => <div className=\"p-2 {$class}\" />;\n");

    $result = runLogicalCssCheck($repo);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('resources/js/Box.tsx');

    File::deleteDirectory($repo);
})->with(['ml-2', 'mr-2', 'pl-2', 'pr-2', 'left-0', 'right-0', 'text-left', 'text-right', 'md:-ml-2', 'hover:pr-4']);

it('fails on a physical utility in package source and css', function (string $path) {
    $repo = plantedRepo($path, "x { @apply ml-2; }\nconst a = 'ml-2';\n");

    expect(runLogicalCssCheck($repo)->exitCode())->toBe(1);

    File::deleteDirectory($repo);
})->with(['packages/ui/src/a.tsx', 'packages/core/src/b.ts', 'resources/css/extra.css']);

it('passes logical utilities and prose that only looks physical', function () {
    $repo = plantedRepo('resources/js/Box.tsx', "// The right-side panel and a left-over.\nexport const Box = () => <div className=\"ms-2 me-2 ps-2 pe-2 start-0 end-0 text-start text-end\" />;\n");

    expect(runLogicalCssCheck($repo)->exitCode())->toBe(0);

    File::deleteDirectory($repo);
});

it('leaves the ported style sheets alone', function () {
    $repo = plantedRepo('resources/css/styles/style-x.css', ".cn-a { @apply ml-2 text-left; }\n");

    expect(runLogicalCssCheck($repo)->exitCode())->toBe(0);

    File::deleteDirectory($repo);
});
