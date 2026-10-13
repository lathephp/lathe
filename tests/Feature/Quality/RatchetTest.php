<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A repo whose main branch has the given floors, then a branch that changes them.
 *
 * @param  array<string, array<string, int>>  $base
 * @param  array<string, array<string, int>>  $head
 */
function ratchet(array $base, array $head): ProcessResult
{
    $repo = sys_get_temp_dir().'/ratchet-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($repo);
    $git = 'git -c user.email=t@example.test -c user.name=Test';
    File::put("{$repo}/coverage-floors.json", json_encode($base));
    Process::path($repo)->run("git init -q -b main && git add -A && {$git} commit -qm base && git checkout -qb change")->throw();
    File::put("{$repo}/coverage-floors.json", json_encode($head));
    Process::path($repo)->run("git add -A && {$git} commit -qm change")->throw();

    $result = Process::path($repo)->run(['node', dirname(__DIR__, 3).'/scripts/check-ratchet.mjs', 'main']);
    File::deleteDirectory($repo);

    return $result;
}

it('fails when a change lowers a floor', function () {
    $result = ratchet(['lib/**' => ['statements' => 90]], ['lib/**' => ['statements' => 85]]);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('lib/** statements: 90 -> 85');
});

it('fails when a change removes a floor', function () {
    expect(ratchet(['lib/**' => ['statements' => 90]], [])->exitCode())->toBe(1);
});

it('passes when floors stay or rise', function () {
    expect(ratchet(['lib/**' => ['statements' => 90]], ['lib/**' => ['statements' => 92], 'new/**' => ['statements' => 50]])->exitCode())->toBe(0);
});
