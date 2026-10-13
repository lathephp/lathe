<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// The banned word and ticket ids are built from pieces so this file never holds them.
function bannedWord(): string
{
    return 'La'.'xa';
}

function runWordCheck(string $repo): ProcessResult
{
    return Process::path($repo)->run(['bash', dirname(__DIR__, 3).'/scripts/check-word.sh']);
}

function plantRepo(callable $plant): string
{
    $repo = sys_get_temp_dir().'/word-check-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($repo);
    Process::path($repo)->run('git init -q -b main && git config user.email t@example.test && git config user.name Test');
    File::put("$repo/README.md", "clean\n");
    $plant($repo);
    Process::path($repo)->run('git add -A && git commit -qm "Start the repo"')->throw();

    return $repo;
}

it('prints 0 hits on the tree', function () {
    $result = runWordCheck(dirname(__DIR__, 3));

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toContain('0 hits');
});

it('fails and lists a planted hit', function (Closure $plant, string $expected) {
    $repo = plantRepo($plant);

    $result = runWordCheck($repo);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain($expected);

    File::deleteDirectory($repo);
})->with([
    'tracked content' => [fn (string $repo) => File::put("$repo/notes.txt", 'built on '.bannedWord()."\n"), 'notes.txt'],
    'file name' => [fn (string $repo) => File::put("$repo/".strtolower(bannedWord()).'-file.txt', "x\n"), strtolower(bannedWord()).'-file.txt'],
    'built output' => [function (string $repo) {
        File::put("$repo/.gitignore", "/dist/\n");
        File::ensureDirectoryExists("$repo/dist");
        File::put("$repo/dist/app.js", 'window.'.bannedWord().'=1;');
    }, 'dist/app.js'],
    'commit message' => [fn (string $repo) => Process::path($repo)->run('git commit -q --allow-empty -m "Port from '.bannedWord().'"'), 'commit'],
    'old ticket id' => [function (string $repo) {
        File::ensureDirectoryExists("$repo/tests");
        File::put("$repo/tests/SomeTest.php", '// see '.'L-0'.'42'."\n");
    }, 'tests/SomeTest.php'],
]);
