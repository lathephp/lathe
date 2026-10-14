<?php

it('names both reporting channels and the fix time in the security policy', function () {
    $policy = (string) file_get_contents(dirname(__DIR__, 3).'/SECURITY.md');

    expect($policy)->toContain('private vulnerability reporting')
        ->toContain('security@lathephp.com')
        ->toContain('7 days')
        ->toMatch('/^\| Version \|/m');
});

it('lists the supported platforms on the support page', function () {
    $page = (string) file_get_contents(dirname(__DIR__, 3).'/docs/getting-started/support.md');

    expect($page)->toContain('PHP 8.4+', 'Laravel 13', 'MySQL 8.4+', 'MariaDB 10.11+', 'PostgreSQL 15+', 'SQLite');
});

it('makes the maintainer own the speed baseline and the API snapshots', function () {
    $owners = (string) file_get_contents(dirname(__DIR__, 3).'/.github/CODEOWNERS');

    expect($owners)->toMatch('#^/tests/Benchmark/baseline\.json @\S+$#m')
        ->toMatch('#^/api/ @\S+$#m');
});
