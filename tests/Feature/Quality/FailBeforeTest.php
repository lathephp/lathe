<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function failBeforeRepo(string $baseFlag, string $headFlag): string
{
    $repo = sys_get_temp_dir().'/fail-before-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$repo}/src");
    File::ensureDirectoryExists("{$repo}/tests");
    File::put("{$repo}/src/flag.txt", "{$baseFlag}\n");
    // The fake runner passes when the source flag reads "fixed".
    File::put("{$repo}/runner.sh", "#!/usr/bin/env bash\ntest \"\$(cat src/flag.txt)\" = fixed\n");
    $git = 'git -c user.name=t -c user.email=t@t';
    Process::path($repo)->run("git init -q -b main && git add -A && {$git} commit -qm base && git checkout -qb change")->throw();

    File::put("{$repo}/src/flag.txt", "{$headFlag}\n");
    File::put("{$repo}/tests/NewBehaviourTest.php", "<?php\n");
    Process::path($repo)->run("git add -A && {$git} commit -qm change")->throw();

    return $repo;
}

function runFailBefore(string $repo): ProcessResult
{
    return Process::path($repo)
        ->env(['LATHE_TEST_COMMAND' => 'bash runner.sh'])
        ->run(['bash', dirname(__DIR__, 3).'/scripts/fail-before.sh']);
}

it('passes when the new tests fail on the base source and pass on the head', function () {
    $repo = failBeforeRepo('broken', 'fixed');

    $result = runFailBefore($repo);

    expect($result->exitCode())->toBe(0, $result->output())
        ->and(trim(File::get("{$repo}/src/flag.txt")))->toBe('fixed');

    File::deleteDirectory($repo);
});

it('fails when the new tests also pass on the base source', function () {
    $repo = failBeforeRepo('fixed', 'fixed');

    $result = runFailBefore($repo);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('also pass on the base');

    File::deleteDirectory($repo);
});

it('fails when the new tests do not pass on the head', function () {
    $repo = failBeforeRepo('broken', 'broken');

    $result = runFailBefore($repo);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('fail on the head');

    File::deleteDirectory($repo);
});
