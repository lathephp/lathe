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

it('defines the six jobs', function () {
    expect(array_keys(ciWorkflow()['jobs']))
        ->toBe(['php', 'php-lowest', 'scaffold', 'quality', 'frontend', 'browser']);
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
