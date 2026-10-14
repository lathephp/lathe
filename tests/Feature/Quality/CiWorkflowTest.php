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
            ->and(array_values(array_unique($secrets[1])))->toBeIn([[], ['GITHUB_TOKEN']]);
    }
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
