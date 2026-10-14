<?php

use Symfony\Component\Yaml\Yaml;

function ciWorkflow(): array
{
    return Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/ci.yml');
}

function ciSteps(string $job): string
{
    return collect(ciWorkflow()['jobs'][$job]['steps'])->pluck('run')->filter()->implode("\n");
}

it('defines the eight required jobs and the two scheduled ones', function () {
    expect(array_keys(ciWorkflow()['jobs']))->toBe([
        'php', 'php-lowest', 'bench-guards', 'scaffold', 'octane', 'quality', 'frontend', 'browser',
        'upstream', 'audit',
    ]);
});

it('runs the required jobs on pull requests and pushes, not on the schedule', function () {
    foreach (['php', 'php-lowest', 'bench-guards', 'scaffold', 'octane', 'quality', 'frontend', 'browser'] as $job) {
        expect(ciWorkflow()['jobs'][$job]['if'] ?? null)->toBe("github.event_name != 'schedule'");
    }
});

it('runs the upstream and audit jobs only on the schedule', function () {
    expect(ciWorkflow()['jobs']['upstream']['if'])->toBe("github.event_name == 'schedule'")
        ->and(ciWorkflow()['jobs']['audit']['if'])->toBe("github.event_name == 'schedule'");
});

it('points each database cell at its own database', function (string $job, array $connections) {
    $config = ciWorkflow()['jobs'][$job];
    $cells = collect($config['strategy']['matrix']['include'])->pluck('connection', 'database')->all();

    expect($cells)->toBe($connections)
        ->and($config['env']['DB_CONNECTION'])->toBe('${{ matrix.connection }}')
        ->and($config['services']['database']['image'])
        ->toBe("\${{ matrix.database != 'sqlite' && matrix.database || '' }}");
})->with([
    'php' => ['php', ['sqlite' => 'sqlite', 'mysql:8.4' => 'mysql', 'mariadb:10.11' => 'mariadb', 'postgres:15' => 'pgsql']],
    'php-lowest' => ['php-lowest', ['sqlite' => 'sqlite', 'mysql:8.4' => 'mysql']],
    'bench-guards' => ['bench-guards', ['sqlite' => 'sqlite', 'mysql:8.4' => 'mysql', 'mariadb:10.11' => 'mariadb', 'postgres:15' => 'pgsql']],
]);

it('runs the benchmark group on PHP 8.5 and the four databases', function () {
    $matrix = ciWorkflow()['jobs']['bench-guards']['strategy']['matrix'];

    expect($matrix['php'])->toBe(['8.5'])
        ->and($matrix['database'])->toBe(['sqlite', 'mysql:8.4', 'mariadb:10.11', 'postgres:15'])
        ->and(ciSteps('bench-guards'))->toContain('vendor/bin/pest --group=benchmark');
});

it('runs the Octane lane on PHP 8.5 and MySQL 8.4', function () {
    $setup = collect(ciWorkflow()['jobs']['octane']['steps'])->firstWhere('uses', 'shivammathur/setup-php@v2');

    // The lane script starts its own MySQL container, so the image is named there.
    expect($setup['with']['php-version'])->toBe('8.5')
        ->and(ciSteps('octane'))->toContain('bash scripts/octane-lane.sh')
        ->and(file_get_contents(dirname(__DIR__, 3).'/scripts/lanes.sh'))->toContain('docker.io/library/mysql:8.4');
});

it('runs the php job on both PHP versions and the four databases', function () {
    $matrix = ciWorkflow()['jobs']['php']['strategy']['matrix'];

    expect($matrix['php'])->toBe(['8.4', '8.5'])
        ->and($matrix['database'])->toBe(['sqlite', 'mysql:8.4', 'mariadb:10.11', 'postgres:15']);
});

it('runs php-lowest on PHP 8.4 with the lowest dependencies', function () {
    $job = ciWorkflow()['jobs']['php-lowest'];

    expect($job['strategy']['matrix']['php'])->toBe(['8.4'])
        ->and($job['strategy']['matrix']['database'])->toBe(['sqlite', 'mysql:8.4'])
        ->and(ciSteps('php-lowest'))->toContain('composer update --prefer-lowest --prefer-stable');
});

it('installs from the committed lock files', function () {
    expect(ciSteps('php'))->toContain('composer install')
        ->and(ciSteps('frontend'))->toContain('pnpm install --frozen-lockfile');
});

it('fails the frontend job on a production licence that is not allowed', function () {
    expect(ciSteps('frontend'))->toContain('node scripts/third-party-licenses.mjs');
});

it('checks the bundle size, the locale keys and the theme contrast', function () {
    expect(ciSteps('frontend'))->toContain('pnpm size')
        ->toContain('node scripts/check-translations.mjs')
        ->toContain('node scripts/check-contrast.mjs');
});

it('uses no token for a package registry or another host', function () {
    foreach (glob(dirname(__DIR__, 3).'/.github/workflows/*') as $file) {
        $text = (string) file_get_contents($file);
        preg_match_all('/secrets\.(\w+)/', $text, $secrets);

        expect($text)->not->toContain('NPM_TOKEN')
            ->not->toContain('NODE_AUTH_TOKEN')
            // RELEASE_DISPATCH_TOKEN is a github.com token that starts the site build and the benchmark re-run.
            ->and(array_diff(array_unique($secrets[1]), ['GITHUB_TOKEN', 'RELEASE_DISPATCH_TOKEN']))->toBe([]);
    }
});

it('can be called by the release workflow and keeps each event in its own concurrency group', function () {
    expect(ciWorkflow()['on'])->toHaveKey('workflow_call')
        ->and(ciWorkflow()['concurrency']['group'])->toBe('ci-${{ github.event_name }}-${{ github.ref }}');
});

it('runs the weekly schedule on Monday at 04:00 UTC', function () {
    expect(ciWorkflow()['on']['schedule'])->toBe([['cron' => '0 4 * * 1']]);
});

it('lists all five browser projects', function () {
    $steps = ciSteps('browser');

    expect($steps)->toContain('composer test:browser');

    foreach (['desktop-chrome', 'desktop-firefox', 'desktop-safari', 'phone-android', 'phone-ios'] as $project) {
        expect($steps)->toContain($project);
    }
});

it('pins the pnpm version that pnpm/action-setup installs', function () {
    $package = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/package.json'), true);

    expect($package['packageManager'] ?? null)->toMatch('/^pnpm@\d+\.\d+\.\d+$/');
});

it('gives the quality job Node, pnpm and the whole history the word check reads', function () {
    $steps = collect(ciWorkflow()['jobs']['quality']['steps']);
    $runs = ciSteps('quality');

    expect($steps->pluck('uses')->filter()->values()->all())->toContain('pnpm/action-setup@v4', 'actions/setup-node@v4')
        ->and($steps->firstWhere('uses', 'actions/checkout@v4')['with']['fetch-depth'] ?? null)->toBe(0)
        ->and(strpos($runs, 'pnpm install --frozen-lockfile'))->toBeLessThan(strpos($runs, 'composer lint'))
        ->and($steps->firstWhere('run', 'bash scripts/check-word.sh')['env']['LATHE_PR_TEXT'] ?? null)->toContain('github.event.pull_request.title');
});

it('makes the screenshot baselines when none are committed and uploads them', function () {
    $steps = collect(ciWorkflow()['jobs']['browser']['steps']);
    $missing = "steps.baselines.outputs.missing == 'true'";
    $upload = $steps->firstWhere('uses', 'actions/upload-artifact@v4');

    expect($steps->firstWhere('id', 'baselines')['run'])->toContain('tests/.pest/snapshots/Browser/VisualTest')
        ->and($steps->firstWhere('if', $missing)['run'])
        ->toContain('pest tests/Browser/VisualTest.php --update-snapshots')
        ->toContain('--browser firefox')
        ->toContain('--browser safari')
        ->and($upload['if'])->toBe("{$missing} && !cancelled()")
        ->and($upload['with'])->toBe([
            'name' => 'screenshot-baselines',
            'path' => 'tests/.pest/snapshots/Browser/VisualTest',
            // The baselines sit under the hidden .pest folder, which the action skips by default.
            'include-hidden-files' => true,
            'if-no-files-found' => 'error',
        ]);
});
