<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function changelogRepo(): string
{
    $repo = sys_get_temp_dir().'/changelog-check-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$repo}/src");
    File::ensureDirectoryExists("{$repo}/packages/core/src");
    File::put("{$repo}/src/Thing.php", "<?php\n");
    File::put("{$repo}/packages/core/src/a.ts", "export {};\n");
    File::put("{$repo}/CHANGELOG.md", "# Changelog\n");
    Process::path($repo)->run('git init -q -b main && git add -A && git -c user.name=t -c user.email=t@t commit -qm base && git checkout -qb change')->throw();

    return $repo;
}

/**
 * @param  array<string, string>  $files
 */
function commitChange(string $repo, array $files): void
{
    foreach ($files as $path => $content) {
        File::ensureDirectoryExists(dirname("{$repo}/{$path}"));
        File::put("{$repo}/{$path}", $content);
    }

    Process::path($repo)->run('git add -A && git -c user.name=t -c user.email=t@t commit -qm change')->throw();
}

function runChangelogCheck(string $repo, string $labels = ''): ProcessResult
{
    return Process::path($repo)
        ->env(['LATHE_PR_LABELS' => $labels])
        ->run(['bash', dirname(__DIR__, 3).'/scripts/check-changelog.sh']);
}

it('fails a source change that leaves the changelog alone', function (string $path) {
    $repo = changelogRepo();
    commitChange($repo, [$path => "changed\n"]);

    $result = runChangelogCheck($repo, 'fix');

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('CHANGELOG.md');

    File::deleteDirectory($repo);
})->with([
    'package source' => ['src/Thing.php'],
    'workspace package source' => ['packages/core/src/a.ts'],
]);

it('passes a source change that touches the changelog', function () {
    $repo = changelogRepo();
    commitChange($repo, ['src/Thing.php' => "changed\n", 'CHANGELOG.md' => "# Changelog\n\n## Unreleased\n- A fix\n"]);

    expect(runChangelogCheck($repo)->exitCode())->toBe(0);

    File::deleteDirectory($repo);
});

it('passes a source change labelled no-changelog', function () {
    $repo = changelogRepo();
    commitChange($repo, ['src/Thing.php' => "changed\n"]);

    $result = runChangelogCheck($repo, 'fix no-changelog');

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toContain('no-changelog');

    File::deleteDirectory($repo);
});

it('passes a change that leaves the sources alone', function () {
    $repo = changelogRepo();
    commitChange($repo, ['docs/guide.md' => "words\n"]);

    expect(runChangelogCheck($repo)->exitCode())->toBe(0);

    File::deleteDirectory($repo);
});
