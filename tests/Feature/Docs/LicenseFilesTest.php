<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('carries the MIT licence of Lareys at the root and in every package', function () {
    $root = dirname(__DIR__, 3);
    $licence = File::get("{$root}/LICENSE");

    expect($licence)->toStartWith("MIT License\n")
        ->toContain('Copyright (c) 2026 Lareys', 'Permission is hereby granted, free of charge');

    foreach ([...glob("{$root}/packages/*", GLOB_ONLYDIR) ?: [], ...glob("{$root}/plugins/*", GLOB_ONLYDIR) ?: []] as $package) {
        expect("{$package}/LICENSE")->toBeFile()
            ->and(File::get("{$package}/LICENSE"))->toBe($licence);
    }
});

it('declares MIT in every manifest', function () {
    $root = dirname(__DIR__, 3);
    $manifests = [...glob("{$root}/composer.json") ?: [], ...glob("{$root}/plugins/*/composer.json") ?: [], ...glob("{$root}/package.json") ?: [], ...glob("{$root}/packages/*/package.json") ?: []];

    expect($manifests)->toHaveCount(6);

    foreach ($manifests as $manifest) {
        expect(json_decode(File::get($manifest), true)['license'] ?? null)->toBe('MIT', $manifest);
    }
});

/**
 * @param  array<string, list<array{name: string, versions: list<string>}>>  $licenses
 */
function writeThirdPartyLicenses(array $licenses): array
{
    $dir = sys_get_temp_dir().'/third-party-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/licenses.json", json_encode($licenses, JSON_THROW_ON_ERROR));

    $result = Process::run(['node', dirname(__DIR__, 3).'/scripts/third-party-licenses.mjs', '--input', "{$dir}/licenses.json", '--output', "{$dir}/THIRD-PARTY-LICENSES.md"]);

    return [$result, $dir];
}

it('lists each production dependency with its licence', function () {
    [$result, $dir] = writeThirdPartyLicenses([
        'MIT' => [['name' => 'react', 'versions' => ['19.2.0']]],
        'MIT AND ISC' => [['name' => 'victory-vendor', 'versions' => ['37.3.6']]],
    ]);

    expect($result->exitCode())->toBe(0)
        ->and(File::get("{$dir}/THIRD-PARTY-LICENSES.md"))->toContain('| react | 19.2.0 | MIT |', '| victory-vendor | 37.3.6 | MIT AND ISC |');

    File::deleteDirectory($dir);
})->group('frontend');

it('fails on a production dependency whose licence is not allowed', function () {
    [$result, $dir] = writeThirdPartyLicenses([
        'MIT' => [['name' => 'react', 'versions' => ['19.2.0']]],
        'GPL-3.0' => [['name' => 'copyleft-thing', 'versions' => ['1.0.0']]],
    ]);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('copyleft-thing@1.0.0 (GPL-3.0)')
        ->and("{$dir}/THIRD-PARTY-LICENSES.md")->not->toBeFile();

    File::deleteDirectory($dir);
})->group('frontend');
