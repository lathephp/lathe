<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// These run Node scripts; the PHP-only lanes leave the frontend group out.
pest()->group('frontend');

function runContrastScript(string $script, string ...$args): ProcessResult
{
    $root = dirname(__DIR__, 3);

    return Process::path($root)->run(['node', "{$root}/scripts/{$script}", ...$args]);
}

/**
 * A temp copy of a package file with replacements planted in it.
 *
 * @param  array<string, string>  $replace
 * @return array{string, string} the folder and the path of the copy
 */
function plantedCopy(string $file, array $replace): array
{
    $dir = sys_get_temp_dir().'/contrast-check-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($dir);
    File::put("{$dir}/".basename($file), strtr(File::get(dirname(__DIR__, 3)."/{$file}"), $replace));

    return [$dir, "{$dir}/".basename($file)];
}

it('exits 0 on the shipped styles', function () {
    $result = runContrastScript('check-contrast.mjs');

    expect($result->exitCode())->toBe(0, $result->output().$result->errorOutput());
});

it('fails on a primary-foreground planted at 4:1 on primary', function () {
    // Light primary is oklch(0.205 0 0); a grey of lightness 0.57 sits near 4:1 on it.
    [$dir, $css] = plantedCopy('resources/css/app.css', ['--primary-foreground: oklch(0.985 0 0);' => '--primary-foreground: oklch(0.57 0 0);']);

    $result = runContrastScript('check-contrast.mjs', $css);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('light: primary-foreground on primary is 4.0');

    File::deleteDirectory($dir);
});

it('fails on a ring planted at 2:1 on the background', function () {
    [$dir, $css] = plantedCopy('resources/css/app.css', ['--ring: oklch(0.64 0 0);' => '--ring: oklch(0.8 0 0);']);

    $result = runContrastScript('check-contrast.mjs', $css);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('light: ring on background is 1.');

    File::deleteDirectory($dir);
});
