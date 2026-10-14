<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A small repo shaped like Lathe's, with the release script and the licence script copied in.
 */
function releaseRepo(): string
{
    $root = dirname(__DIR__, 3);
    $repo = sys_get_temp_dir().'/release-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists("{$repo}/scripts");
    File::ensureDirectoryExists("{$repo}/packages/core");
    File::copy("{$root}/scripts/release.sh", "{$repo}/scripts/release.sh");
    File::copy("{$root}/scripts/third-party-licenses.mjs", "{$repo}/scripts/third-party-licenses.mjs");
    File::copy("{$root}/scripts/upgrade-guide.mjs", "{$repo}/scripts/upgrade-guide.mjs");
    File::ensureDirectoryExists("{$repo}/docs/getting-started");
    File::put("{$repo}/.gitignore", "/dist/\n");
    File::put("{$repo}/package.json", "{\"name\": \"lathe\", \"version\": \"1.0.0-dev\"}\n");
    File::put("{$repo}/packages/core/package.json", "{\"name\": \"@lathephp/core\", \"version\": \"1.0.0-dev\"}\n");
    File::put("{$repo}/CHANGELOG.md", "# Changelog\n\n## [Unreleased]\n\n## [1.0.0-beta.1] - Unreleased\n\n- First.\n");
    File::put("{$repo}/licenses.json", json_encode(['MIT' => [['name' => 'react', 'versions' => ['19.2.0']]]]));
    File::put("{$repo}/lane.json", json_encode(['header' => ['cpu' => 'test'], 'metrics' => [['name' => 'list_p95', 'value' => 12.5, 'unit' => 'ms', 'budget' => 50]]]));
    Process::path($repo)->run('git init -q -b main && git config user.email t@example.test && git config user.name Test && git add -A && git commit -qm "Start" && git init -q --bare ../'.basename($repo).'-origin.git && git remote add origin ../'.basename($repo).'-origin.git')->throw();

    return $repo;
}

function runRelease(string $repo, string $version = '1.0.0-beta.1'): ProcessResult
{
    return Process::path($repo)
        ->env(['LATHE_RELEASE_BUILD' => 'mkdir -p dist && echo built > dist/app.js', 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json"])
        ->run(['bash', 'scripts/release.sh', $version, "{$repo}/lane.json"]);
}

it('cuts the release branch and tag with the build, and pushes nothing', function () {
    $repo = releaseRepo();

    $result = runRelease($repo);
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and($result->output())->toContain('Nothing was pushed')
        ->and($git('git rev-parse v1.0.0-beta.1^{commit}'))->toBe($git('git rev-parse release/1.0.0-beta.1'))
        ->and($git('git show v1.0.0-beta.1:dist/app.js'))->toBe('built')
        ->and($git('git ls-tree main dist'))->toBe('')
        ->and(json_decode($git('git show v1.0.0-beta.1:packages/core/package.json'), true)['version'])->toBe('1.0.0-beta.1')
        ->and($git('git show v1.0.0-beta.1:CHANGELOG.md'))->toMatch('/^## \[1\.0\.0-beta\.1\] - \d{4}-\d{2}-\d{2}$/m')
        ->and($git('git show v1.0.0-beta.1:THIRD-PARTY-LICENSES.md'))->toContain('| react | 19.2.0 | MIT |')
        ->and(json_decode($git('git show v1.0.0-beta.1:tests/Benchmark/baseline.json'), true))->toMatchArray(['release' => 'v1.0.0-beta.1'])
        ->and($git('git ls-remote ../'.basename($repo).'-origin.git'))->toBe('');

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
});

it('refuses a dirty tree, a version without a changelog section and a tag that exists', function () {
    $repo = releaseRepo();

    File::put("{$repo}/stray.txt", "x\n");
    expect(runRelease($repo)->errorOutput())->toContain('not clean');
    File::delete("{$repo}/stray.txt");

    expect(runRelease($repo, '1.0.0-beta.2')->errorOutput())->toContain('no \'## [1.0.0-beta.2] - Unreleased\' section');

    Process::path($repo)->run('git tag v1.0.0-beta.1')->throw();
    expect(runRelease($repo)->errorOutput())->toContain('tag v1.0.0-beta.1 exists');

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
});
