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
    Process::path($repo)->run('git init -q -b main && git config user.email t@example.test && git config user.name Test && git add -A && git commit -qm "Start" && git init -q --bare ../'.basename($repo).'-origin.git && git remote add origin ../'.basename($repo).'-origin.git')->throw();

    return $repo;
}

function runRelease(string $repo, string $version = '1.0.0-beta.1'): ProcessResult
{
    return Process::path($repo)
        ->env(['LATHE_RELEASE_AUDIT' => 'true', 'LATHE_RELEASE_BUILD' => 'mkdir -p dist && echo built > dist/app.js', 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json", 'LATHE_RELEASE_SMOKE_COMMAND' => 'true'])
        ->run(['bash', 'scripts/release.sh', $version]);
}

it('cuts the release branch and tag with the build, and pushes nothing', function () {
    $repo = releaseRepo();

    $result = runRelease($repo);
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and($result->output())->toContain('Nothing was pushed')
        ->and($result->output())->toContain('release: the audit passed: true')
        ->and($result->output())->toContain('release: LATHE_RELEASE_BUILD stands in for pnpm build')
        ->and($result->output())->toContain('release: LATHE_RELEASE_LICENSES_JSON stands in for the licence list from pnpm')
        ->and($result->output())->toContain('release: the smoke install passed: true')
        ->and($git('git rev-parse v1.0.0-beta.1^{commit}'))->toBe($git('git rev-parse release/1.0.0-beta.1'))
        ->and($git('git show v1.0.0-beta.1:dist/app.js'))->toBe('built')
        ->and($git('git ls-tree main dist'))->toBe('')
        ->and(json_decode($git('git show v1.0.0-beta.1:packages/core/package.json'), true)['version'])->toBe('1.0.0-beta.1')
        ->and($git('git show v1.0.0-beta.1:CHANGELOG.md'))->toMatch('/^## \[1\.0\.0-beta\.1\] - \d{4}-\d{2}-\d{2}$/m')
        ->and($git('git show v1.0.0-beta.1:THIRD-PARTY-LICENSES.md'))->toContain('| react | 19.2.0 | MIT |')
        ->and($git('git ls-tree -r --name-only v1.0.0-beta.1 tests'))->toBe('')
        ->and($git('git ls-remote ../'.basename($repo).'-origin.git'))->toBe('');

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
})->group('frontend');

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
})->group('frontend');

it('refuses a cut while entries sit under ## [Unreleased], before it looks for the version section', function () {
    $repo = releaseRepo();
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());

    File::put("{$repo}/CHANGELOG.md", "# Changelog\n\n## [Unreleased]\n\n### Fixed\n\n- A fix.\n\n## [1.0.0-beta.1] - Unreleased\n\n- First.\n");
    Process::path($repo)->run('git commit -qam "Note a fix"')->throw();

    foreach (['1.0.0-beta.1', '1.0.0-beta.10'] as $version) {
        $result = runRelease($repo, $version);

        expect($result->exitCode())->toBe(1)
            ->and($result->errorOutput())->toContain('release: CHANGELOG.md has entries under ## [Unreleased]')
            ->and($git('git branch --show-current'))->toBe('main')
            ->and($git('git branch --list "release/*"'))->toBe('');
    }

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
})->group('frontend');

it('cuts with an empty ## [Unreleased] heading followed by blank lines', function () {
    $repo = releaseRepo();

    File::put("{$repo}/CHANGELOG.md", "# Changelog\n\n## [Unreleased]\n\n\n   \n## [1.0.0-beta.1] - Unreleased\n\n- First.\n");
    Process::path($repo)->run('git commit -qam "Space out the changelog"')->throw();

    $result = runRelease($repo);

    expect($result->exitCode())->toBe(0, $result->errorOutput());

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
})->group('frontend');

it('refuses a cut whose audit fails, before the release branch exists', function () {
    $repo = releaseRepo();
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());

    $result = Process::path($repo)
        ->env(['LATHE_RELEASE_AUDIT' => 'echo advisory >&2; exit 1', 'LATHE_RELEASE_BUILD' => 'mkdir -p dist && echo built > dist/app.js', 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json", 'LATHE_RELEASE_SMOKE_COMMAND' => 'true'])
        ->run(['bash', 'scripts/release.sh', '1.0.0-beta.1']);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toMatch('/^release: .*audit/m')
        ->and($git('git branch --show-current'))->toBe('main')
        ->and($git('git branch --list "release/*"'))->toBe('')
        ->and($git('git tag --list'))->toBe('');

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
})->group('frontend');

// The release workflow smokes the tag only after it is pushed, and Packagist keeps a pushed tag.
it('refuses a cut whose smoke install fails, leaving main as it was and no tag', function () {
    $repo = releaseRepo();
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());

    $result = Process::path($repo)
        ->env(['LATHE_RELEASE_AUDIT' => 'true', 'LATHE_RELEASE_BUILD' => 'mkdir -p dist && echo built > dist/app.js', 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json", 'LATHE_RELEASE_SMOKE_COMMAND' => 'test -f dist/app.js && echo could not pin >&2 && exit 1'])
        ->run(['bash', 'scripts/release.sh', '1.0.0-beta.1']);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('could not pin')
        ->and($result->errorOutput())->toMatch('/^release: the smoke install failed/m')
        ->and($git('git tag --list'))->toBe('')
        ->and($git('git branch --show-current'))->toBe('main')
        ->and($git('git branch --list "release/*"'))->toBe('')
        ->and($git('git status --porcelain'))->toBe('');

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
})->group('frontend');

it('runs composer audit --locked and then pnpm audit --prod --audit-level=high when no audit command is set', function (string $failing) {
    $repo = releaseRepo();
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());
    $bin = "{$repo}-bin";
    File::ensureDirectoryExists($bin);
    foreach (['composer', 'pnpm'] as $tool) {
        $code = $tool === $failing ? 1 : 0;
        File::put("{$bin}/{$tool}", "#!/usr/bin/env bash\necho \"{$tool} \$*\" >> {$repo}-calls.log\necho \"No advisories from {$tool}\"\nexit {$code}\n");
        chmod("{$bin}/{$tool}", 0755);
    }

    $result = Process::path($repo)
        ->env(['PATH' => $bin.PATH_SEPARATOR.getenv('PATH'), 'LATHE_RELEASE_BUILD' => 'mkdir -p dist && echo built > dist/app.js', 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json", 'LATHE_RELEASE_SMOKE_COMMAND' => 'true'])
        ->run(['bash', 'scripts/release.sh', '1.0.0-beta.1']);
    $calls = File::get("{$repo}-calls.log");

    if ($failing === 'none') {
        expect($result->exitCode())->toBe(0, $result->errorOutput())
            ->and($calls)->toBe("composer audit --locked\npnpm audit --prod --audit-level=high\n")
            ->and($result->output())->toMatch('/^release: composer audit --locked passed$.*^release: pnpm audit --prod --audit-level=high passed$.*^release: the two builds of dist\/ match, 1 file$.*^Tagged v1\.0\.0-beta\.1 /ms');
    } else {
        expect($result->exitCode())->toBe(1)
            ->and($result->errorOutput())->toContain("release: {$failing} audit")
            ->and($calls)->toStartWith('composer audit --locked')
            ->and($git('git branch --show-current'))->toBe('main')
            ->and($git('git branch --list "release/*"'))->toBe('');
    }

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
    File::deleteDirectory($bin);
    File::delete("{$repo}-calls.log");
})->with(['none', 'composer', 'pnpm'])->group('frontend');

it('refuses a cut whose two builds of dist differ, leaving main as it was, and cuts on the next run', function () {
    $repo = releaseRepo();
    $git = fn (string $command) => trim(Process::path($repo)->run($command)->output());
    $build = fn (string $command) => Process::path($repo)
        ->env(['LATHE_RELEASE_AUDIT' => 'true', 'LATHE_RELEASE_BUILD' => $command, 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json", 'LATHE_RELEASE_SMOKE_COMMAND' => 'true'])
        ->run(['bash', 'scripts/release.sh', '1.0.0-beta.1']);
    $main = fn () => [File::get("{$repo}/package.json"), File::get("{$repo}/packages/core/package.json"), File::get("{$repo}/CHANGELOG.md")];
    $before = $main();

    $refused = $build('mkdir -p dist && echo built > dist/app.js && date +%s%N > dist/app.css');

    expect($refused->exitCode())->toBe(1)
        ->and($refused->errorOutput())->toMatch('/^release: .*dist\/app\.css/m')
        ->and($refused->errorOutput())->not->toContain('dist/app.js')
        ->and($git('git tag --list'))->toBe('')
        ->and($git('git branch --show-current'))->toBe('main')
        ->and($git('git branch --list "release/*"'))->toBe('')
        ->and($git('git status --porcelain'))->toBe('')
        ->and($main())->toBe($before);

    $cut = $build("echo build >> {$repo}-builds.log && mkdir -p dist && echo built > dist/app.js && echo same > dist/app.css");

    expect($cut->exitCode())->toBe(0, $cut->errorOutput())
        ->and(File::get("{$repo}-builds.log"))->toBe("build\nbuild\n")
        ->and($git('git show v1.0.0-beta.1:dist/app.js'))->toBe('built')
        ->and($git('git show v1.0.0-beta.1:dist/app.css'))->toBe('same');

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
    File::delete("{$repo}-builds.log");
})->group('frontend');

it('compares the files two builds write, not what the build prints, and refuses a build that fails', function () {
    $repo = releaseRepo();
    $build = fn (string $command) => Process::path($repo)
        ->env(['LATHE_RELEASE_AUDIT' => 'true', 'LATHE_RELEASE_BUILD' => $command, 'LATHE_RELEASE_LICENSES_JSON' => "{$repo}/licenses.json", 'LATHE_RELEASE_SMOKE_COMMAND' => 'true'])
        ->run(['bash', 'scripts/release.sh', '1.0.0-beta.1']);

    $failed = $build('mkdir -p dist && echo same > dist/app.js && false');

    expect($failed->exitCode())->toBe(1)
        ->and($failed->errorOutput())->toContain('release: the build failed')
        ->and(trim(Process::path($repo)->run('git tag --list')->output()))->toBe('');

    $chatty = $build('echo "built in $(date +%s%N) ms" && mkdir -p dist && echo same > dist/app.js');

    expect($chatty->exitCode())->toBe(0, $chatty->errorOutput());

    File::deleteDirectory($repo);
    File::deleteDirectory("{$repo}-origin.git");
})->group('frontend');

it('names both of its refusals in its header comment', function () {
    $header = implode("\n", array_slice(file(dirname(__DIR__, 3).'/scripts/release.sh'), 0, 15));

    expect($header)->toContain('entries under ## [Unreleased]')
        ->and($header)->toContain('composer audit --locked')
        ->and($header)->toContain('pnpm audit --prod --audit-level=high')
        ->and($header)->toContain('scripts/release-smoke.sh');
});
