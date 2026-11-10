<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A repo with lanes.sh and a stand-in Octane lane that only says it ran, with origin/main at its first commit.
 */
function lanesRepo(): string
{
    $root = dirname(__DIR__, 3);
    $repo = sys_get_temp_dir().'/lanes-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists("{$repo}/scripts");
    File::ensureDirectoryExists("{$repo}/workbench");
    File::ensureDirectoryExists("{$repo}/resources");
    File::copy("{$root}/scripts/lanes.sh", "{$repo}/scripts/lanes.sh");
    File::put("{$repo}/scripts/octane-lane.sh", "echo octane lane ran\n");
    File::put("{$repo}/workbench/app.php", "<?php\n");
    File::put("{$repo}/resources/app.tsx", "export {}\n");
    Process::path($repo)->run('git init -q -b main && git config user.email t@example.test && git config user.name Test && git add -A && git commit -qm "Start" && git update-ref refs/remotes/origin/main HEAD')->throw();

    return $repo;
}

function runChangedOctane(string $repo): ProcessResult
{
    return Process::path($repo)->run(['bash', 'scripts/lanes.sh', 'changed', 'octane']);
}

it('runs the Octane lane when a workbench or Octane test file differs from origin/main', function () {
    $repo = lanesRepo();

    $quiet = runChangedOctane($repo);
    File::put("{$repo}/resources/app.tsx", "export const a = 1\n");
    $frontend = runChangedOctane($repo);
    File::put("{$repo}/workbench/app.php", "<?php // changed\n");
    $workbench = runChangedOctane($repo);

    expect($quiet->exitCode())->toBe(0)
        ->and($quiet->output())->toContain('no workbench or tests/Octane file differs from origin/main')
        ->and($frontend->output())->not->toContain('octane lane ran')
        ->and($workbench->exitCode())->toBe(0, $workbench->errorOutput())
        ->and($workbench->output())->toContain('octane lane ran');

    File::deleteDirectory($repo);
});

it('gives each changed run a database of its own and drops it after', function () {
    $repo = lanesRepo();
    File::ensureDirectoryExists("{$repo}/bin");
    File::ensureDirectoryExists("{$repo}/tests");
    File::put("{$repo}/bin/podman", "#!/usr/bin/env bash\necho \"\$*\" >> {$repo}/podman.log\n");
    File::put("{$repo}/bin/php", "#!/usr/bin/env bash\necho \"pest on \$DB_DATABASE\"\n");
    chmod("{$repo}/bin/podman", 0755);
    chmod("{$repo}/bin/php", 0755);
    File::put("{$repo}/tests/ProbeTest.php", "<?php\n");
    $run = fn () => Process::path($repo)->env(['PATH' => "{$repo}/bin:".getenv('PATH')])->run(['bash', 'scripts/lanes.sh', 'changed', 'pgsql']);

    $first = $run();
    $second = $run();
    preg_match('/pest on (\S+)/', $first->output(), $a);
    preg_match('/pest on (\S+)/', $second->output(), $b);
    $log = File::get("{$repo}/podman.log");

    expect($first->exitCode())->toBe(0, $first->errorOutput())
        ->and($a[1] ?? null)->toStartWith('lathe_test_')
        ->and($b[1] ?? null)->toStartWith('lathe_test_')
        ->and($a[1])->not->toBe($b[1])
        ->and($log)->toContain("create database {$a[1]}")
        ->and($log)->toContain("drop database if exists {$a[1]}")
        ->and($log)->toContain("drop database if exists {$b[1]}");

    File::deleteDirectory($repo);
});
